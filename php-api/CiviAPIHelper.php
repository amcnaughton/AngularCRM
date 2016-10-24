<?php

/**
 * @version     1.0.0
 * @package     com_exchange
 * @copyright   Copyright (C) 2015. All rights reserved.
 * @license
 * @author      Allan McNaughton <allan@keshavconsulting.com> -
 */
defined('_JEXEC') or die;

class CiviAPIHelper
{
    static function formatAddress($args, $separator = '<br>')
    {
        if(!empty($args['street_address']))
            $address = $args['street_address'] . $separator;

        if (empty($args['city']))
            $address .=  $args['state'];
        else
            if (empty($args['state']))
                $address .=  $args['city'];
            else
                $address .= "{$args['city']}, {$args['state']}";

        if(!empty($args['postal_code']))
            if(empty($address))
                $address = $args['postal_code'];
            else
                $address .= ' '  . $args['postal_code'];

        return $address;
    }

    static function formatPhoneNumber($s)
    {
        if(empty($s))
            return "";
        $rx = "/
            (1)?\D*     # optional country code
            (\d{3})?\D* # optional area code
            (\d{3})\D*  # first three
            (\d{4})     # last four
            (?:\D+|$)   # extension delimiter or EOL
            (\d*)       # optional extension
        /x";

        preg_match($rx, $s, $matches);
        if (!isset($matches[0])) return false;

        $country = $matches[1];
        $area = $matches[2];
        $three = $matches[3];
        $four = $matches[4];
        $ext = $matches[5];

        $out = "$three-$four";
        if (!empty($area)) $out = "$area-$out";
        if (!empty($country)) $out = "+$country-$out";
        if (!empty($ext)) $out .= "x$ext";

        return $out;
    }
}