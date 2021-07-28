<?php


namespace Sysbot\Utils;


class Serializer
{

    /**
     * @param string $string
     * @return string
     */
    protected static function rleEncode(string $string): string
    {
        $new = '';
        $count = 0;
        $null = chr(0);
        foreach (str_split($string) as $cur) {
            if ($cur === $null) {
                $count++;
            } else {
                if ($count > 0) {
                    $new .= $null . chr($count);
                    $count = 0;
                }
                $new .= $cur;
            }
        }
        return $new;
    }

    /**
     * @param string $string
     * @return string
     */
    protected static function rleDecode(string $string): string
    {
        $new = '';
        $last = '';
        $null = chr(0);
        foreach (str_split($string) as $cur) {
            if ($last === $null) {
                $new .= str_repeat($last, ord($cur));
                $last = '';
            } else {
                $new .= $last;
                $last = $cur;
            }
        }
        return $new . $last;
    }

    /**
     * @param string $string
     * @return string
     */
    protected static function base64UrlDecode(string $string): string
    {
        return base64_decode(str_pad(strtr($string, '-_', '+/'), strlen($string) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * @param string $string
     * @return string
     */
    protected static function base64UrlEncode(string $string): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }


}