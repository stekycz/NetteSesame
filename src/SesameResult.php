<?php

namespace stekycz\NetteSesame;

use Nette\Object;
use SimpleXMLElement;



class SesameResult extends Object
{

	/**
	 * @var \SimpleXMLElement
	 */
	private $xml;



	/**
	 * @param string $responseBody
	 */
	public function __construct($responseBody)
	{
		$this->xml = new SimpleXMLElement($responseBody);
	}



	/**
	 * @return string[]
	 */
	public function getHeaders()
	{
		$headers = array();
		foreach ($this->xml->head->variable as $header) {
			$headers[] = (string) $header['name'];
		}

		return $headers;
	}



	/**
	 * Returns the rows in an array format, false if there are no rows.
	 *
	 * @return array|bool
	 */
	public function getRows()
	{
		$rows = array();
		foreach ($this->xml->results->result as $result) {
			$row = array();
			foreach ($result->binding as $binding) {
				$name = (string) $binding['name'];
				if ($binding->literal) {
					$row[$name] = (string) $binding->literal;
					$row[$name . '_type'] = 'literal';
				} else {
					if ($binding->uri) {
						$row[$name] = (string) $binding->uri;
						$row[$name . '_type'] = 'uri';
					} else {
						if ($binding->bnode) {
							$row[$name] = (string) $binding->bnode;
							$row[$name . '_type'] = 'bnode';
						}
					}
				}
			}
			$rows[] = $row;
		}

		if (count($rows) <= 0) {
			return FALSE;
		}

		return $rows;
	}



	/**
	 * Returns whether the result contains any rows.
	 *
	 * @return bool
	 */
	public function hasRows()
	{
		foreach ($this->xml->results->result as $result) {
			return TRUE;
		}

		return FALSE;
	}

}
