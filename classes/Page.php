<?php

class Page {
	protected $context = array();
	protected $p;

	function initialize($p, $context) {
		$this->p = $p;
		$this->context = $context;
	}

	function getIndexes() {
		return array();
	}

	function getMenuOptions() {
		return array();
	}

	public function callControllers() {
		$this->controllerMeta();
		$this->controllerHeader();
		$this->controllerMenu();
		$this->controllerLeftPane();
		$this->controllerMidPane();
		$this->controllerRightPane();
		$this->controllerFooter();
	}

	public function controllerMeta() {

	}

	public function controllerHeader() {

	}

	public function controllerMenu() {

	}

	public function controllerLeftPane() {

	}

	public function controllerMidPane() {

	}

	public function controllerRightPane() {

	}

	public function controllerFooter() {

	}

	public function viewMeta($xml) {

	}

	public function viewHeader($xml) {

	}

	public function viewMenu($xml) {

	}

	public function viewLeftPane($xml) {

	}

	public function viewMidPane($xml) {

	}

	public function viewRightPane($xml) {

	}

	public function viewFooter($xml) {

	}
}

