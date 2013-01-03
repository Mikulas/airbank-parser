<?php

class Airbank
{

	const URL_LOGIN = 'https://ib.airbank.cz/';
	const URL_IB = 'https://ib.airbank.cz/ib';



	/** @var object */
	private $html;



	/**
	 * Performes login
	 * blocking
	 * @param $username string
	 * @param $username string
	 */
	public function __construct($username, $password)
	{
		// get login target
		$html = $this->getHtml(self::URL_LOGIN);
		$onclick = $html->find('#id27')[0]->onclick;
		$match = [];
		preg_match("~wicketSubmitFormById\('idc',\s+'\?x=(?P<x>[^']+)',~si", $onclick, $match);

		// post login
		$this->html = $this->getHtml(self::URL_LOGIN, ['x' => $match['x'], 'random' => $this->getRandom()], [
			'idc_hf_0' => '',
			'login:componentWrapper:component' => $username,
			'passwd:componentWrapper:component' => $password,
			'baseContainer:basePanel:contentWrapper:content:loginPanel:submit:wrapper:button' => 1,
		]);
	}



	/**
	 * @return string User-set account name
	 */
	public function getAccountName()
	{
		return trim($this->html->find('th.mhtAccountItemLeft')[0]->plaintext);
	}



	/**
	 * @return string Generated account number
	 */
	public function getAccount()
	{
		return trim($this->html->find('td.mhtAccountItemLeft')[0]->plaintext);
	}



	/**
	 * @return float With blocked transactions deduced
	 */
	public function getRealBalance()
	{
		return $this->parseAmount(trim($this->html->find('th.mhtAccountItemRight')[0]->plaintext));
	}



	/**
	 * @return float Only performed transactions
	 */
	public function getBalance()
	{
		return $this->parseAmount(trim($this->html->find('td.mhtAccountItemRight')[0]->plaintext));
	}



	/**
	 * @return array
	 */
	public function getRecentTransactions()
	{
		$ts = [];
		foreach ($this->html->find('table.mhtTableLinks tr') as $t) {
			$ts[] = [
				'group' => trim($t->find('.uiW80')[0]->plaintext),
				'date' => trim($t->find('.uiW80')[1]->plaintext),
				'account' => trim($t->find('.uiW240 div div')[0]->plaintext),
				'note' => trim($t->find('.uiW240 div div')[1]->plaintext),
				'amount' => $this->parseAmount(trim($t->find('.uiW120')[0]->plaintext)),
			];
		}

		return $ts;
	}



	private function getRandom()
	{
		return mt_rand(1e10, 1e11 - 1) / 1e11;
	}



	private function getHtml($url, array $get = [], array $post = [], $ajax = FALSE)
	{
		$url = $url . ($get ? '?' . http_build_query($get) : '');
		$jar = __DIR__ . '/cookies';

		$c = curl_init();

		$headers = [
			'Accept:text/xml',
			'Accept-Charset:ISO-8859-2,utf-8;q=0.7,*;q=0.3',
			'Accept-Language:en-US,en;q=0.8',
			'Connection:keep-alive',
			'Host:ib.airbank.cz',
			'Origin:https://ib.airbank.cz',
			'Referer:https://ib.airbank.cz/',
		];
		if ($ajax) {
			$headers[] = 'Wicket-Ajax:true';
		}

		curl_setopt_array($c, [
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => 'Airbank_parser/1.0',
			CURLOPT_POST => (bool) count($post),
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_HEADER => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_COOKIEJAR => $jar,
			CURLOPT_COOKIEFILE => $jar,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => $headers,
		]);
		if ($post) {
			curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
		}

		$res = curl_exec($c);
		curl_close($c);

		return str_get_html($res);
	}



	private function getPage($x)
	{
		$this->getHtml(self::URL_IB, ['x' => $x, 'random' => $this->getRandom()], [], TRUE);
	}



	private function parseAmount($str)
	{
		$str = str_replace(' CZK', '', $str);
		$str = str_replace('&nbsp;', '', $str);
		$str = str_replace(' ', '', $str);
		$str = str_replace(' ', '', $str); // thinspace
		return (float) str_replace(',', '.', $str);
	}

}
