<?php

namespace stekycz\NetteSesame;

use Kdyby\Curl;
use Nette\Object;



class SesameClient extends Object
{

	const LANGUAGE_SPARQL = 'sparql';
	const LANGUAGE_SERQL = 'serql';

	const OUTPUT_MIME_SPARQL_XML = 'application/sparql-results+xml';
	const OUTPUT_MIME_SPARQL_JSON = 'application/sparql-results+json'; // Unsupported - No results handler
	const OUTPUT_MIME_BINARY_TABLE = 'application/x-binary-rdf-results-table'; // Unsupported - No results handler
	const OUTPUT_MIME_BOOLEAN = 'text/boolean'; // Unsupported - No results handler

	const INPUT_MIME_RDFXML = 'application/rdf+xml';
	const INPUT_MIME_NTRIPLES = 'text/plain';
	const INPUT_MIME_TURTLE = 'application/x-turtle';
	const INPUT_MIME_N3 = 'text/rdf+n3';
	const INPUT_MIME_TRIX = 'application/trix';
	const INPUT_MIME_TRIG = 'application/x-trig';

	// Unsupported, needs more documentation (http://www.franz.com/agraph/allegrograph/doc/http-protocol.html#header3-67)
	// const INPUT_MIME_RDFTRANSACTION = 'application/x-rdftransaction';

	/**
	 * @var string[]
	 */
	private static $validInputFormats = array(
		self::INPUT_MIME_RDFXML,
		self::INPUT_MIME_NTRIPLES,
		self::INPUT_MIME_TURTLE,
		self::INPUT_MIME_N3,
		self::INPUT_MIME_TRIX,
		self::INPUT_MIME_TRIG,
	);

	/**
	 * @var string Connection string
	 */
	private $dsn;

	/**
	 * @var string The selected repository
	 */
	private $repository;



	/**
	 * @param string $sesameUrl Sesame server connection string
	 * @param string $repository The repository name
	 */
	public function __construct($sesameUrl = 'http://localhost:8080/openrdf-sesame', $repository = NULL)
	{
		$this->dsn = $sesameUrl;
		$this->setRepository($repository);
	}



	/**
	 * Selects a repository to work on.
	 *
	 * @param string $repository The repository name
	 * @return \stekycz\NetteSesame\SesameClient
	 */
	public function setRepository($repository)
	{
		$this->repository = $repository;

		return $this;
	}



	/**
	 * Returns a list of all the available repositories on the Sesame installation.
	 *
	 * @return \stekycz\NetteSesame\SesameResult
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function listRepositories()
	{
		$request = new Curl\Request($this->dsn . '/repositories');
		$request->headers['Accept'] = self::OUTPUT_MIME_SPARQL_XML;

		try {
			$response = $request->get();
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Sesame engine response error.', $e->getCode(), $e);
		}

		return new SesameResult($response->getResponse());
	}



	private function checkRepository()
	{
		if (empty($this->repository) || $this->repository == '') {
			throw new NotSelectedRepositoryException('Please supply some available repository.');
		}
	}



	private static function checkQueryLang($queryLang)
	{
		if (!in_array($queryLang, array(self::LANGUAGE_SPARQL, self::LANGUAGE_SERQL))) {
			throw new UnsupportedQueryLanguageException('Please supply a valid query language, SPARQL or SeRQL supported.');
		}
	}



	private static function checkResultFormat($format)
	{
		if ($format != self::OUTPUT_MIME_SPARQL_XML) {
			throw new UnsupportedResultFormatException('Please supply a valid result format, SPARQL+XML supported.');
		}
	}



	private static function checkContext($context)
	{
		if ($context != 'null') {
			$context = (substr($context, 0, 1) != '<' || substr($context, strlen($context) - 1, 1) != '>') ? "<$context>" : $context;
			$context = urlencode($context);
		}

		return $context;
	}



	private static function checkInputFormat($format)
	{
		if (!in_array($format, self::$validInputFormats)) {
			throw new UnsupportedInputFormatException('Please supply a valid input format.');
		}
	}



	/**
	 * Performs a simple Query.
	 *
	 * Performs a query and returns the result in the selected format. Throws an exception
	 * if the query returns an error.
	 *
	 * @param string $query String used for query
	 * @param string $resultFormat Returned result format, see const definitions for supported list.
	 * @param string $queryLang Language used for querying, SPARQL and SeRQL supported
	 * @param bool $infer Use inference in the query
	 * @return \stekycz\NetteSesame\SesameResult
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function query($query, $resultFormat = self::OUTPUT_MIME_SPARQL_XML, $queryLang = self::LANGUAGE_SPARQL, $infer = TRUE)
	{
		$this->checkRepository();
		self::checkQueryLang($queryLang);
		self::checkResultFormat($resultFormat);

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository);
		$request->headers['Accept'] = self::OUTPUT_MIME_SPARQL_XML;

		try {
			$response = $request->post(array(
				'query' => $query,
				'queryLn' => $queryLang,
				'infer' => $infer,
			));
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to run query, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return new SesameResult($response->getResponse());
	}



	/**
	 * Appends data to the selected repository.
	 *
	 * @param string $data Data in the supplied format
	 * @param string $context The context the query should be run against
	 * @param string $inputFormat See class const definitions for supported formats
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function append($data, $context = 'null', $inputFormat = self::INPUT_MIME_RDFXML)
	{
		$this->checkRepository();
		$context = self::checkContext($context);
		self::checkInputFormat($inputFormat);

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/statements?context=' . $context);
		$request->headers['Content-type'] = $inputFormat;

		try {
			$request->post($data);
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to append data to the repository, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return TRUE;
	}



	/**
	 * Appends data to the selected repository.
	 *
	 * @param string $filePath The filepath of data, can be a URL
	 * @param string $context The context the query should be run against
	 * @param string $inputFormat See class const definitions for supported formats
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function appendFile($filePath, $context = 'null', $inputFormat = self::INPUT_MIME_RDFXML)
	{
		$data = $this->getFile($filePath);
		return $this->append($data, $context, $inputFormat);
	}



	/**
	 * Overwrites data in the selected repository, can optionally take a context parameter.
	 *
	 * @param string $data Data in the supplied format
	 * @param string $context The context the query should be run against
	 * @param string $inputFormat See class const definitions for supported formats
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function overwrite($data, $context = 'null', $inputFormat = self::INPUT_MIME_RDFXML)
	{
		$this->checkRepository();
		$context = self::checkContext($context);
		self::checkInputFormat($inputFormat);

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/statements?context=' . $context);
		$request->headers['Content-type'] = $inputFormat;

		try {
			$request->put($data);
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to append data to the repository, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return TRUE;
	}



	/**
	 * Overwrites data in the selected repository, can optionally take a context parameter.
	 *
	 * @param string $filePath The filepath of data, can be a URL
	 * @param string $context The context the query should be run against
	 * @param string $inputFormat See class const definitions for supported formats
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function overwriteFile($filePath, $context = 'null', $inputFormat = self::INPUT_MIME_RDFXML)
	{
		$data = $this->getFile($filePath);
		return $this->overwrite($data, $context, $inputFormat);
	}



	/**
	 * @param string $filePath The filepath of data, can be a URL
	 * @return string
	 */
	private function getFile($filePath)
	{
		if (empty($filePath) || $filePath == '') {
			throw new FileNotSpecifiedException('Please supply a filepath.');
		} elseif (!file_exists($filePath)) {
			throw new FileNotFoundException('Please supply an exist filepath.');
		} elseif (!is_readable($filePath)) {
			throw new FileNotReadableException('Please supply a filepath of readable file.');
		}

		return file_get_contents($filePath);
	}



	/**
	 * Gets the namespace URL for the supplied prefix.
	 *
	 * @param string $prefix Data in the supplied format
	 * @return string The URL of the namespace
	 */
	public function getNamespace($prefix)
	{
		$this->checkRepository();

		if (empty($prefix)) {
			throw new PrefixNotSpecifiedException('Please supply a prefix.');
		}

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/namespaces/' . $prefix);
		$request->headers['Accept'] = 'text/plain';

		try {
			$response = $request->get();
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to get namespace, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return (string) $response->getResponse();
	}



	/**
	 * Sets the the namespace for the specified prefix
	 *
	 * @param string $prefix Data in the supplied format
	 * @param string $namespace The context the query should be run against
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function setNamespace($prefix, $namespace)
	{
		$this->checkRepository();

		if (empty($prefix)) {
			throw new PrefixNotSpecifiedException('Please supply a prefix.');
		}
		if (empty($namespace)) {
			throw new NamespaceNotSpecifiedException('Please supply a namesapce.');
		}

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/namespaces/' . $prefix);
		$request->headers['Content-type'] = 'text/plain';

		try {
			$request->put($namespace);
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to set the namespace, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return TRUE;
	}



	/**
	 * Deletes the the namespace for the specified prefix.
	 *
	 * @param string $prefix Data in the supplied format
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function deleteNamespace($prefix)
	{
		$this->checkRepository();

		if (empty($prefix)) {
			throw new PrefixNotSpecifiedException('Please supply a prefix.');
		}

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/namespaces/' . $prefix);

		try {
			$request->delete();
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to delete the namespace, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return TRUE;
	}



	/**
	 * Returns a list of all the contexts in the repository.
	 *
	 * @param string $resultFormat Returned result format, see const definitions for supported list
	 * @return \stekycz\NetteSesame\SesameResult
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function contexts($resultFormat = self::OUTPUT_MIME_SPARQL_XML)
	{
		$this->checkRepository();
		self::checkResultFormat($resultFormat);

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/contexts');
		$request->headers['Accept'] = self::OUTPUT_MIME_SPARQL_XML;

		try {
			$response = $request->post();
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to run query, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return new SesameResult($response->getResponse());
	}



	/**
	 * Returns the size of the repository.
	 *
	 * @param string $context The context the query should be run against
	 * @return int
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function size($context = 'null')
	{
		$this->checkRepository();

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/size?context=' . $context);
		$request->headers['Accept'] = 'text/plain';

		try {
			$response = $request->post();
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to run query, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return (int) $response->getResponse();
	}



	/**
	 * Clears the repository. Removes all data from the selected repository from ALL contexts.
	 *
	 * @return bool
	 * @throws \stekycz\NetteSesame\BadStatusException
	 */
	public function clear()
	{
		$this->checkRepository();

		$request = new Curl\Request($this->dsn . '/repositories/' . $this->repository . '/statements');

		try {
			$request->delete();
		} catch (Curl\BadStatusException $e) {
			throw new BadStatusException('Failed to clear repository, HTTP response error: ' . $e->getCode(), $e->getCode(), $e);
		}

		return TRUE;
	}

}
