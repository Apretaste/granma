<?php

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;

class Granma extends Service
{

	public $client;

	/**
	 * Crawler client
	 *
	 * @return \Goutte\Client
	 */
	public function getClient()
	{
		if (is_null($this->client)) {
			$this->client = new Client();
			$guzzle = new GuzzleClient(["verify" => false]);
			$this->client->setClient($guzzle);
		}
		return $this->client;
	}

	/**
	 * Get crawler for URL
	 *
	 * @param string $url
	 *
	 * @return \Symfony\Component\DomCrawler\Crawler
	 */
	protected function getCrawler($url = "")
	{
		$url = trim($url);
		if ($url != '' && $url[0] == '/') $url = substr($url, 1);

		$crawler = $this->getClient()->request("GET", $url);

		return $crawler;
	}

	private function getUrl($url, &$info = [])
	{
		$url = str_replace("//", "/", $url);
		$url = str_replace("http:/","http://", $url);
		$url = str_replace("https:/","https://", $url);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);

		$default_headers = [
			"Cache-Control" => "max-age=0",
			"Origin" => "{$url}",
			"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36",
			"Content-Type" => "application/x-www-form-urlencoded"
		];

		$hhs = [];
		foreach ($default_headers as $key => $val)
			$hhs[] = "$key: $val";

		curl_setopt($ch, CURLOPT_HTTPHEADER, $hhs);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$html = curl_exec($ch);

		$info = curl_getinfo($ch);

		if (isset($info['redirect_url']) && $info['redirect_url'] != $url)
			return $this->getUrl($info['redirect_url'], $info);

		curl_close($ch);

		return $html;
	}

	/**
	 * Function executed when the service is called
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _main(Request $request)
	{
		$response = new Response();
		$response->setCache("day");
		$response->setResponseSubject("Noticias de hoy");
		$response->createFromTemplate("allStories.tpl", $this->allStories());
		return $response;
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _buscar(Request $request)
	{
		// no allow blank entries
		if (empty($request->query)) {
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("Busqueda en blanco");
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que tema desea leer");
			return $response;
		}

		// search by the query
		try {
			$articles = $this->searchArticles($request->query);
		} catch (Exception $e) {
			return $this->respondWithError();
		}

		// error if the searche return empty
		if (empty($articles)) {
			$response = new Response();
			$response->setResponseSubject("Su busqueda no genero resultados");
			$response->createFromText("Su busqueda <b>{$request->query}</b> no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.");
			return $response;
		}

		$responseContent = array(
			"articles" => $articles,
			"search" => $request->query
		);

		$response = new Response();
		$response->setResponseSubject("Buscar: " . $request->query);
		$response->createFromTemplate("searchArticles.tpl", $responseContent);
		return $response;
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _historia(Request $request)
	{
		// no allow blank entries
		if (empty($request->query)) {
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("Busqueda en blanco");
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos que articulo quiere leer");
			return $response;
		}

		// send the actual response
		try {
			$responseContent = $this->story($request->query);
		} catch (Exception $e) {
			return $this->respondWithError();
		}

		// get the image if exist
		$images = array();
		if (!empty($responseContent['img'])) {
			$images = array($responseContent['img']);
		}

		$response = new Response();
		$response->setCache();
		$response->setResponseSubject("La historia que usted pidio");
		$response->createFromTemplate("story.tpl", $responseContent, $images);
		return $response;
	}

	/**
	 * Call list by categoria
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _categoria(Request $request)
	{
		if (empty($request->query)) {
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("Categoria en blanco");
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que categor&iacute;a desea leer");
			return $response;
		}

		$responseContent = array(
			"articles" => $this->listArticles($request->query)["articles"],
			"category" => $request->query
		);

		$response = new Response();
		$response->setResponseSubject("Categoria: " . $request->query);
		$response->createFromTemplate("catArticles.tpl", $responseContent);
		return $response;
	}

	/**
	 * Search stories
	 *
	 * @param String
	 * @return Array
	 * */
	private function searchArticles($query)
	{
		// Setup crawler
		$url = "http://www.granma.cu/archivo?q=" . urlencode($query);
		$crawler = $this->getCrawler($url);

		// Collect search by term
		$articles = array();

		$crawler->filter('div.col-md-12.g-searchpage-results article.g-searchpage-story')->each(function ($item, $i) use (&$articles) {
			// only allow news, no media or gallery
			if ($item->filter('.ico')->count() > 0) return;

			// get data from each row
			$title = $item->filter('h2 a')->text();
			$date = $item->filter('p.g-story-meta')->text();
			$description = $item->filter('p')->text();
			$link = $item->filter('a')->attr("href");

			// store list of articles
			$articles[] = array(
				"pubDate" => $date,
				"description" => $description,
				"title" => $title,
				"link" => $link
			);
		});


		return $articles;
	}

	/**
	 * Get the array of news by content
	 *
	 * @param String
	 * @return Array
	 */
	private function listArticles($query)
	{
		//tuve que usar simplexml debido a que el feed provee los datos dentro de campos cdata
		$page = $this->getUrl("http://www.granma.cu/feed");
		$content = simplexml_load_string($page, null, LIBXML_NOCDATA);

		$articles = array();
		foreach ($content->channel->item as $item) {
			// if category matches, add to list of articles
			foreach ($item->category as $cat) {
				if (strtoupper($cat) == strtoupper($query)) {
					// get all parameters
					$title = $item->title;
					$link = $this->urlSplit($item->link);
					$description = strip_tags($item->description);
					$pubDate = $item->pubDate;
					$dc = $item->children("http://purl.org/dc/elements/1.1/");
					$author = $dc->creator;

					$articles[] = array(
						"title" => (String)$title,
						"link" => (String)$link,
						"pubDate" => (String)$pubDate,
						"description" => (String)$description,
						"author" => (String)$author
					);
				}
			}
		}

		// Return response content
		return array("articles" => $articles);
	}

	/**
	 * Get all stories from a query
	 *
	 * @return Array
	 */
	private function allStories()
	{
		$f = fopen("../logs/granma.log", "a");

		// create a crawler
		$info = [];
		$page = $this->getUrl("http://www.granma.cu/feed",, $info);
		$content = simplexml_load_string($page, null, LIBXML_NOCDATA);
		fputs($f, "allStories: ".serialize($info)."\n");
		fputs($f, substr($page, 0, 300)."\n");
		$articles = array();
		foreach ($content->channel->item as $item) {
			// get all parameters
			$title = $this->utils->removeTildes($item->title);
			$link = $this->urlSplit($item->link);
			$description = $this->utils->removeTildes(strip_tags($item->description));
			$pubDate = $item->pubDate;
			$dc = $item->children("http://purl.org/dc/elements/1.1/");
			$author = $this->utils->removeTildes($dc->creator);

			$category = array();
			foreach ($item->category as $currCategory) {
				$category[] = $this->utils->removeTildes((String)$currCategory);
			}

			$articles[] = array(
				"title" => (String)$title,
				"link" => (String)$link,
				"pubDate" => (String)$pubDate,
				"description" => (String)$description,
				"category" => $category,
				"categoryLink" => array(),
				"author" => (String)$author
			);
		}
		fclose($f);
		// return response content
		return array("articles" => $articles);
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 * @return Array
	 */
	private function story($query)
	{
		// create a crawler
		$crawler = $this->getCrawler("http://www.granma.cu/$query");

		// search for title
		$title = $crawler->filter('div.g-story-meta h1.g-story-heading')->text();

		// get the intro

		$titleObj = $crawler->filter('div.g-story-meta p.g-story-description');
		$intro = $titleObj->count() > 0 ? $titleObj->text() : "";

		// get the images
		$imageObj = $crawler->filter('div.image img');
		$imgAlt = "";
		$img = "";
		if ($imageObj->count() != 0) {
			$imgUrl = trim($imageObj->attr("src"));
			$imgAlt = trim($imageObj->attr("alt"));

			// get the image
			if (!empty($imgUrl)) {
				$img = $this->utils->getTempDir() . $this->utils->generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
				file_put_contents($img, file_get_contents("http://www.granma.cu$imgUrl"));
				$this->utils->optimizeImage($img, 300);
			}
		}

		// get the array of paragraphs of the body
		$paragraphs = $crawler->filter('div.story-body-text.story-content p');
		$content = array();
		foreach ($paragraphs as $p) {
			$content[] = trim($p->textContent);
		}

		// create a json object to send to the template
		return array(
			"title" => $title,
			"intro" => $intro,
			"img" => $img,
			"imgAlt" => $imgAlt,
			"content" => $content,
			"url" => "http://www.granma.cu/$query"
		);
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 * @return String
	 * http://www.martinoticias.com/content/blah
	 */
	private function urlSplit($url)
	{
		$url = explode("/", trim($url));
		unset($url[0]);
		unset($url[1]);
		unset($url[2]);
		return implode("/", $url);
	}

	/**
	 * Return a generic error email, usually for try...catch blocks
	 *
	 * @auhor salvipascual
	 * @return Respose
	 */
	private function respondWithError()
	{
		error_log("WARNING: ERROR ON SERVICE GRANMA");

		$response = new Response();
		$response->setResponseSubject("Error en peticion");
		$response->createFromText("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. Por favor intente nuevamente mas tarde.");
		return $response;
	}
}