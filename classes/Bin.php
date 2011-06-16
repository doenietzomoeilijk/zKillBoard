<?php

class Bin {
	private static $bin = array();

	public static function get($name, $default = null) {
		if (isset(Bin::$bin[$name])) return Bin::$bin[$name];
		return $default;
	}

	public static function set($name, $value) {
		Bin::$bin[$name] = $value;
	}
}
