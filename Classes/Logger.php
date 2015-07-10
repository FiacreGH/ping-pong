<?php

class Logger {

	/**
	 * This method is used to log.
	 *
	 * @param string|array $message
	 * @return void
	 */
	public function log($message = '', $isReturnCarriage = TRUE) {
		$returnCarrage = $isReturnCarriage === TRUE ? chr(10) : '';
		if (is_array($message)) {
			foreach ($message as $line) {
				print $message . $returnCarrage ;
			}
		} else {
			print $message . $returnCarrage;
		}
	}

}