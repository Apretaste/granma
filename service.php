<?php
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
/**
 * Apretaste
 *
 * Granma Service
 *
 * @author @Reyes
 * @author @kumahacker
 * @author @salvipascual
 *
 * @version 2.0
 */
class Service
{

	/**
	 * Function executed when the service is called
	 *
	 * @return Response
	 */
	public function _main(Request $request, Response $response)
	{
		$pathToService = Utils::getPathToService($response->serviceName);
	    $response->setCache("day");
		$response->setLayout('granma.ejs');			
		$response->setTemplate("allStories.ejs", $this->allStories(), ["$pathToService/images/granma-logo.png"]);

		
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _buscar(Request $request, Response $response)
	{
		$buscar = $request->input->data->searchQuery;
		$isCategory = $request->input->data->isCategory == "true";
		// no allow blank entries
		if(empty($buscar)){
			$response->setLayout('granma.ejs');
			$response->setTemplate('text.ejs', [
				"title" => "Su busqueda parece estar en blanco",
				"body" => "debe decirnos sobre que tema desea leer"
			]);

			return;
		}

		// search by the query
		$articles = $this->searchArticles($buscar);
		
		// error if the searche return empty
		if(empty($articles))
		{
			$response->setLayout('granma.ejs');
			$response->setTemplate("text.ejs", [
				"title" => "Su busqueda parece estar en blanco",
				"body" => html_entity_decode("Su busqueda no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.")
			]);

			return;
		}
        
		$responseContent = [
			"articles" => $articles,
			"isCategory" => $isCategory,
			"search" => $buscar
		];

		$response->setLayout('granma.ejs');
		$response->setTemplate("searchArticles.ejs", $responseContent);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _historia(Request $request, Response $response)
	{
		$history = $request->input->data->historia;

		// send the actual response
		$responseContent = $this->story($history);

		// get the image if exist
		$images = array();
		if (!empty($responseContent['img'])) $images = array($responseContent['img']);

		$response->setCache();
		$response->setTemplate("story.ejs", $responseContent, $images);

		if(isset($request->input->data->search)){
			$isCategory = $request->input->data->isCategory;
			$responseContent['backButton'] = "{'command':'GRANMA BUSCAR', 'data':{'searchQuery':'{$request->input->data->search}', 'isCategory':$isCategory}}";
		}
		else $responseContent['backButton'] = "{'command':'GRANMA'}";

		$response->setCache();
		$response->setLayout('granma.ejs');
		$response->setTemplate("story.ejs", $responseContent, $images);
		
	}

	/**
	 * Search stories
	 *
	 * @param String
	 *
	 * @return array
	 */
	private function searchArticles($query)
	{
		// Setup crawler
		$client = new Client();
		$crawler = $client->request('GET',"http://www.granma.cu/archivo?q=" . urlencode($query));

		// Collect search by term
		$articles = [];

		$crawler->filter('div.col-md-12.g-searchpage-results article.g-searchpage-story')->each(function ($item) use (&$articles) {
			// only allow news, no media or gallery
			if ($item->filter('.ico')->count() > 0) return;

			// get data from each row
			$title = $item->filter('h2 a')->text();
			$info = $item->filter('p.g-story-meta')->text();
			$info = explode("de",$info);
			$day = trim($info[0]);
			$month = trim($info[1]);
			$info = explode("@",$info[2]);
			$year = trim($info[0]);
			$info = explode("|",$info[1]);
			$hour = trim($info[0]);
			$author = trim($info[1]);
			$info = "$month $day, $year. $hour &bull; <i>$author</i>";

			$description = $item->filter('p')->text();
			$link = $item->filter('a')->attr("href");

			// store list of articles
			$articles[] = array(
				"pubDate" => $info,
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
	 * @param String $query
	 *
	 * @return array
	 */
	private function searchByCategory($query)
	{
		$client = new Client();
		$page = $client->request('GET',"http://www.granma.cu/feed");
		$content = simplexml_load_string($page, null, LIBXML_NOCDATA);
die(var_dump($content));
		$articles = [];
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
		return ["articles" => $articles];
	}

	/**
	 * Get all stories from a query
	 *
	 * @return array
	 */
	private function allStories()
	{
		// create a crawler
		$page = file_get_contents("http://www.granma.cu/feed");

		$content = simplexml_load_string($page, null, LIBXML_NOCDATA);

		$articles = [];
		if (!isset($content->channel))
			return ["articles" => []];

		foreach ($content->channel->item as $item) {
			// get all parameters
			$title = $item->title;
			$link = $this->urlSplit($item->link);
			$description = strip_tags($item->description);
			setlocale(LC_ALL, 'es_ES.UTF-8');
			$pubDate = $item->pubDate;
			$pubDate = strftime("%B %d, %Y.",strtotime($pubDate))." ".date_format((new DateTime($pubDate)),'h:i a');
			$dc = $item->children("http://purl.org/dc/elements/1.1/");
			$author = $dc->creator;

			$category = array();
			foreach ($item->category as $currCategory) {
				$category[] = (String)$currCategory;
				
			}

			$articles[] = [
				"title" => (String)$title,
				"link" => (String)$link,
				"pubDate" => (String)$pubDate,
				"description" => (String)$description,
				"category" => $category,
				"categoryLink" => array(),
				"author" => (String)$author
			];
		}

		// return response content
		return ["articles" => $articles];
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 * @return array
	 */
	private function story($query)
	{
		$client = new Client();
		$crawler = $client->request('GET',"http://www.granma.cu/$query");

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
				$img = Utils::getTempDir() . Utils::generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
				file_put_contents($img, file_get_contents("http://www.granma.cu$imgUrl"));
			}
		}

		// get the array of paragraphs of the body
		$paragraphs = $crawler->filter('div.story-body-textt p');
		$content = [];
		foreach ($paragraphs as $p) {
			$content[] = trim($p->textContent);
		}

		// create a json object to send to the template
		return [
			"title" => $title,
			"intro" => $intro,
			"img" => $img,
			"imgAlt" => $imgAlt,
			"content" => $content,
			"url" => "http://www.granma.cu/$query"
		];
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
	 * @return Response
	 */
	private function respondWithError()
	{
		error_log("WARNING: ERROR ON SERVICE GRANMA");

		$response->createFromText("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. Por favor intente nuevamente mas tarde.");
		
	}
}
