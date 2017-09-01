<?php
	use Goutte\Client;
	use Symfony\Component\DomCrawler\Crawler;

	class Granma extends Service
	{
		/**
		 * Function executed when the service is called
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _main(Request $request)
		{
			$response = new Response();
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
			if (empty($request->query))
			{
				$response = new Response();
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
			if(empty($articles))
			{
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
			if (empty($request->query))
			{
				$response = new Response();
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
			if( ! empty($responseContent['img']))
			{
				$images = array($responseContent['img']);
			}

			$subject = "La historia que usted pidio";

			$response = new Response();
			$response->setResponseSubject($subject);
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
			if (empty($request->query))
			{
				$response = new Response();
				$response->setResponseSubject("Categoria en blanco");
				$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que categor&iacute;a desea leer");
				return $response;
			}

			$responseContent = array(
				"articles" => $this->listArticles($request->query)["articles"],
				"category" => $request->query
			);

			$response = new Response();
			$response->setResponseSubject("Categoria: ".$request->query);
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
			$client = new Client();
			$url = "http://www.granma.cu/archivo?q=".urlencode($query);
			$crawler = $client->request('GET', $url);

			// Collect saearch by term
			$articles = array();

 			$crawler->filter('div.col-md-12.g-searchpage-results article.g-searchpage-story')->each(function($item, $i) use (&$articles) 
 			{ 
 				// only allow news, no media or gallery 
 				if($item->filter('.ico')->count()>0) return; 
  
 				// get data from each row 
 				$title = $item->filter('h2 a')->text(); 
 				$date = $item->filter('p.g-story-meta')->text(); 
 				$description = $item->filter('p')->text(); 
 				$link = $item->filter('a')->attr("href"); 
  
 				// store list of articles 
 				$articles[] = array( 
 					"pubDate" => $date, 
 					"description" => $description, 
 					"title"	=> $title, 
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
			// Setup crawler
			/*$client = new Client();
			$crawler = $client->request('GET', "http://www.granma.cu/feed"); 

			// Collect articles by category
			$articles = array();
			$crawler->filter('channel item')->each(function($item, $i) use (&$articles, $query)
			{
				// if category matches, add to list of articles
				$item->filter('category')->each(function($cat, $i) use (&$articles, $query, $item)
				{
					if (strtoupper($cat->text()) == strtoupper($query))
					{
						$title = $item->filter('title')->text();
						$link = $this->urlSplit($item->filter('link')->text());
						$pubDate = $item->filter('pubDate')->text();
						$description = $item->filter('description')->text();
						$cadenaAborrar = "/<!-- google_ad_section_start --><!-- google_ad_section_end --><p>/";
						$description = preg_replace($cadenaAborrar, '', $description);
						$description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
						$description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>
						
						$author = "desconocido";
						if ($item->filter('dc|creator')->count() > 0)
						{
							$authorString = trim($item->filter('dc|creator')->text());
							$author = "({$authorString})";
						}

						$articles[] = array(
							"title" => $title,
							"link" => $link,
							"pubDate" => $pubDate,
							"description" => $description,
							"author" => $author
						);
					}
				});
			});*/
			$page = file_get_contents("http://www.granma.cu/feed");
			//tuve que usar simplexml debido a que el feed provee los datos dentro de campos cdata
			$content = simplexml_load_string($page, null, LIBXML_NOCDATA);

			$articles = array();
			foreach ($content->channel->item as $item) {
				// if category matches, add to list of articles
				foreach ($item->category as $cat){
					if (strtoupper($cat) == strtoupper($query)){
						// get all parameters
						$title = $item->title;
						$link = $this->urlSplit($item->link);
						$description = $item->description;
						$pubDate = $item->pubDate;
						$dc = $item->children("http://purl.org/dc/elements/1.1/");
		    			$author = $dc->creator;
						
						$articles[] = array(
							"title" => $title,
							"link" => $link,
							"pubDate" => $pubDate,
							"description" => $description,
							"author" => $author
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
			// create a new client
			//$client = new Client();
			//$guzzle = $client->getClient();
			//$guzzle->setDefaultOption('verify', false);
			//$client->setClient($guzzle);

			// create a crawler
			$page = file_get_contents("http://www.granma.cu/feed");
			//tuve que usar simplexml debido a que el feed provee los datos dentro de campos cdata
			$content = simplexml_load_string($page, null, LIBXML_NOCDATA);
			//$crawler = new Crawler($page);

			//$crawler = $client->request('GET', "http://www.granma.cu/feed"); //http://www.martinoticias.com/api/epiqq

			$articles = array();
			foreach ($content->channel->item as $item) {
				// get all parameters
				$title = $item->title;
				$link = $this->urlSplit($item->link);
				$description = $item->description;
				$pubDate = $item->pubDate;
				$dc = $item->children("http://purl.org/dc/elements/1.1/");
    			$author = $dc->creator;
				$category = array();
				foreach ($item->category as $currCategory)
				{
					$category[] = $currCategory;
				}
				$categoryLink = array();

				$articles[] = array(
					"title" => $title,
					"link" => $link,
					"pubDate" => $pubDate,
					"description" => $description,
					"category" => $category,
					"categoryLink" => $categoryLink,
					"author" => $author
				);
			}
			/*$crawler->filter('channel item')->each(function($item, $i) use (&$articles)
			{

				// get all parameters
				$title = $item->filter('title')->text();
				$comments = $item->filter('comments')->text();
				$pos = strpos($comments, "#");
				$link = $this->urlSplit(substr($comments, 0, $pos));
				$description = $item->filter('description')->text();
				$cadenaAborrar = "/]]>/";
				$description = preg_replace($cadenaAborrar, '', $description);
				$description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
				$description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>
				$pubDate = $item->filter('pubDate')->text();
				$author = $item->filter("creator")->text();
				$category = $item->filter('category')->each(function($category, $j) { return $category->text(); });

				$categoryLink = array();
				foreach ($category as $currCategory)
				{
					$categoryLink[] = $currCategory;
				}

				$articles[] = array(
					"title" => $title,
					"link" => $link,
					"pubDate" => $pubDate,
					"description" => $description,
					"category" => $category,
					"categoryLink" => $categoryLink,
					"author" => $author,
					"tests"=>print_r($category,true)
				);
			});*/

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
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$guzzle->setDefaultOption('verify', false);
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.granma.cu/$query");

			// search for title
			$title = $crawler->filter('div.g-story-meta h1.g-story-heading')->text();

			// get the intro

			$titleObj = $crawler->filter('div.g-story-meta p.g-story-description');
			$intro = $titleObj->count()>0 ? $titleObj->text() : "";

			// get the images
			$imageObj = $crawler->filter('div.image img');
			$imgUrl = ""; $imgAlt = ""; $img = "";
			if ($imageObj->count() != 0)
			{
				$imgUrl = trim($imageObj->attr("src"));
				$imgAlt = trim($imageObj->attr("alt"));

				// get the image
				if ( ! empty($imgUrl))
				{
					$imgName = $this->utils->generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
					$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
					file_put_contents($img, file_get_contents($imgUrl));
					$this->utils->optimizeImage($img, 300);
				}
			}

			// get the array of paragraphs of the body
			$paragraphs = $crawler->filter('div.story-body-text.story-content p');
			$content = array();
			foreach ($paragraphs as $p)
			{
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
?>
