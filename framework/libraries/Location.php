<?php if ( !defined('COREPATH') ) exit;

class Location {
  public static function time($timestamp) {
    return strftime('%X', $timestamp);
  }

  public static function date($timestamp) {
    return strftime('%x', $timestamp);
  }

  public static function date_title($timestamp) {
    return strftime('%A %e %B %Y', $timestamp);
  }

  public static function datetime($timestamp) {
    return trim(str_replace('CET', '', str_replace('CEST', '', strftime('%c', $timestamp))));
  }

  public static function number_format($number) {
    $locale_number_format = localeconv();
    return number_format($number, $locale_number_format['frac_digits'], $locale_number_format['decimal_point'], $locale_number_format['thousands_sep']);
  }

  public static function money_format($number) {
    return money_format('%.2n', $number);
  }

}
