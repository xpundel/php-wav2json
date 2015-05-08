<?php

/**
 * Created by PhpStorm.
 * User: ML
 * Date: 11.07.2014
 * Time: 16:48
 */
class Waveform
{
    const HEADER_LENGTH = 44;

    const LEFT = 1;
    const RIGHT = 2;
    const MID = 3;
    const SIDE = 4;
    const MIN = 5;
    const MAX = 6;

    private $metadata;


    public function getJSON($filename)
    {
        $output = [];
        if (is_readable($filename)) {
            $filesize = filesize($filename);

            if ($filesize < self::HEADER_LENGTH) {
                return false;
            }

            $handle = fopen($filename, 'rb');

            $this->readMetadata($handle);

            $numberOfSamples = $this->metadata['subchunk2']['size'] / ($this->metadata['subchunk1']['bitspersample'] / 8);
            $numberOfFrames = $numberOfSamples / $this->metadata['subchunk1']['numchannels'];

            $samples = min($numberOfFrames, 800);

            $framesPerPixel = ceil(max(1, $numberOfFrames / $samples));
            $samplesPerPixel = $this->metadata['subchunk1']['numchannels'] * $framesPerPixel;

            $blockSize = $samplesPerPixel;
            for ($x = 0; $x < $samples; ++$x) {

                $t = microtime(1);
                $block = [];
                $data = fread($handle, $framesPerPixel * $this->metadata['subchunk1']['numchannels'] * ($this->metadata['subchunk1']['bitspersample'] / 8));

                for ($i = 0; $i < strlen($data); $i += 40) {
                    $block[] = abs(unpack('s', $data[$i].$data[$i+1])[1]);
                }
                $max = max($block);

                $y = $this->map2range($max, 0, 32768, 0, 1); //todo: 32768 - считать 1 << (sizeof(short)*8-1)
                $output[] = $y;
            }

            file_put_contents("qqq", json_encode(["left" => $output]));


        }
    }

    private function readDataShort($handle, $frames)
    {
        $data = [];
        $count = $frames * $this->metadata['subchunk1']['numchannels'];

        for ($i = 0; $i < $count; $i++) {
            $d = $this->readWordSigned($handle);
            $data[] = $d;
        }
        return $data;
    }

    private function map2range($x, $in_min, $in_max, $out_min, $out_max)
    {
        return $this->clamp(
            $out_min + ($out_max - $out_min) * ($x - $in_min) / ($in_max - $in_min),
            $out_min,
            $out_max
        );
    }

    private function clamp($x, $min, $max)
    {
        return max($min, min($max, $x));
    }

    private function computeSample($block, $i, $n_channels, $channel)
    {
        switch ($channel) {
            case self::LEFT :
                return $block[$i];
            case self::RIGHT:
                return $block[$i + 1];
            case self::MID  :
                return ($block[$i] + $block[$i + 1]) / 2;
            case self::SIDE :
                return ($block[$i] - $block[$i + 1]) / 2;
            case self::MIN  :
                return min($block[$i], $block[$i + 1]);
            case self::MAX  :
                return max($block[$i], $block[$i + 1]);
            default :
                break;
        }
        return 0;
    }

    /**
     * @param $handle
     */
    private function readMetadata($handle)
    {
        $this->metadata = array(
            'header' => array(
                'chunkid' => $this->readString($handle, 4),
                'chunksize' => $this->readLong($handle),
                'format' => $this->readString($handle, 4)
            ),
            'subchunk1' => array(
                'id' => $this->readString($handle, 4),
                'size' => $this->readLong($handle),
                'audioformat' => $this->readWord($handle),
                'numchannels' => $this->readWord($handle),
                'samplerate' => $this->readLong($handle),
                'byterate' => $this->readLong($handle),
                'blockalign' => $this->readWord($handle),
                'bitspersample' => $this->readWord($handle)
            ),
            'subchunk2' => array(
                'id' => $this->readString($handle, 4),
                'size' => $this->readLong($handle),
            )
        );
    }

    /**
     * @param $handle
     * @param $length
     * @return mixed
     */
    private function readString($handle, $length)
    {
        return $this->readUnpacked($handle, 'a*', $length);
    }

    /**
     * @param $handle
     * @return mixed
     */
    private function readLong($handle)
    {
        return $this->readUnpacked($handle, 'V', 4);
    }

    /**
     * @param $handle
     * @return mixed
     */
    private function readWord($handle)
    {
        return $this->readUnpacked($handle, 'v', 2);
    }

    /**
     * @param $handle
     * @return mixed
     */
    private function readWordSigned($handle)
    {
        return $this->readUnpacked($handle, 's', 2);
    }

    /**
     * @param $handle
     * @param $type
     * @param $length
     * @return mixed
     */
    private function readUnpacked($handle, $type, $length)
    {
        $bytes = fread($handle, $length);
        $r = unpack($type, $bytes);
        $data = array_pop($r);
        return $data;
    }
}


(new Waveform())->getJSON('1404909144.wav');