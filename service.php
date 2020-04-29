<?php

use Framework\Crawler;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Challenges;
use Framework\Database;
use Framework\Utils;

class Service
{
	/**
	 * Show the list of news
	 */
	public function _main(Request $request, Response &$response)
	{
		$response->setCache('day');
		$response->setLayout('granma.ejs');
		$response->setTemplate('stories.ejs', $this->stories());
	}

	/**
	 * Call to show the news
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _historia(Request $request, Response &$response)
	{
		// send the actual response
		$content = $this->story($request->input->data->historia);

		// get the image if exist
		$images = [];
		if (! empty($content['img'])) {
			$images = [$content['img']];
			$content['img'] = basename($content['img']);
		}

		// complete challenge
		Challenges::complete('read-granma', $request->person->id);

		// send data to the template
		$response->setCache('year');
		$response->setLayout('granma.ejs');
		$response->setTemplate('story.ejs', $content, $images);
	}

	/**
	 * Get all stories from a query
	 *
	 * @return array
	 */
	private function stories()
	{
		// get content from cache
		$cache = TEMP_PATH . 'cache/granma_' . date('Ymd') . '.cache';
		if (file_exists($cache)) {
			$articles = unserialize(file_get_contents($cache));
		}

		// crawl the data from the web
		else {
			// create a crawler
			//$page = Crawler::get('http://www.granma.cu/feed');
			$rss = Feed::loadRss('http://www.granma.cu/feed');
			//$content = simplexml_load_string($page, null, LIBXML_NOCDATA);

			$articles = [];
			/*if (!isset($content->channel)) {
				return ['articles' => []];
			}*/
			$creator = "dc:creator";
			foreach ($rss->item as $item) {
				$link = (string)$item->link;
				$title = Database::escape(quoted_printable_encode(strip_tags((string)$item->title)));
				$author = (string)$item->$creator;
				// get all parameters
				//$title = $item->title;
				//$link = $this->urlSplit($item->link);
				$description = strip_tags($item->description);
				$pubDate = $item->pubDate;
				$pubDate = strftime('%B %d, %Y.', strtotime($pubDate)).' '.date_format((new DateTime($pubDate)), 'h:i a');
				$dc = $item->children('http://purl.org/dc/elements/1.1/');
				$author = $dc->creator;

				// get all the categories
				$category = [];
				foreach ($item->category as $currCategory) {
					$cat = (String) $currCategory;
					if (! in_array($cat, $category)) {
						$category[] = $cat;
					}
				}

				// cut description by 200 chars
				if (strlen($description) > 200) {
					$description = substr($description, 0, 200) . '...';
				}

				// get the article
				$articles[] = [
					'title' => (String) $title,
					'link' => (String) $link,
					'pubDate' => (String) $pubDate,
					'description' => (String) $description,
					'category' => $category,
					'categoryLink' => [],
					'author' => (String) $author
				];
			}

			// create the cache
			file_put_contents($cache, serialize($articles));
		}

		// return response content
		return ['articles' => $articles];
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 * @return array
	 */
	private function story($query)
	{
		// get content from cache
		$cache = TEMP_PATH . 'cache/granma_' . md5($query) . '.cache';
		if (file_exists($cache)) {
			$story = unserialize(file_get_contents($cache));
		}

		// crawl the data from the web
		else {
			Crawler::start("http://www.granma.cu/$query");

			// search for title
			$title = Crawler::filter('div.g-story-meta h1.g-story-heading')->text();

			// get the intro
			$titleObj = Crawler::filter('div.g-story-meta p.g-story-description');
			$intro = $titleObj->count() > 0 ? $titleObj->text() :'';

			// get the images
			$imageObj = Crawler::filter('div.image img');
			$imgAlt = '';
			$img = '';
			if ($imageObj->count() !== 0) {
				$imgUrl = trim($imageObj->attr('src'));
				$imgAlt = trim($imageObj->attr('alt'));

				// get the image
				if (!empty($imgUrl)) {
					$img = TEMP_PATH . 'cache/' . Utils::randomHash() . '.' . pathinfo($imgUrl, PATHINFO_EXTENSION);
					file_put_contents($img, Crawler::get("http://www.granma.cu$imgUrl"));
				}
			}

			// get the array of paragraphs of the body
			$paragraphs = Crawler::filter('div.story-body-textt p');
			$content = [];
			foreach ($paragraphs as $p) {
				$content[] = trim($p->textContent);
			}

			// create a json object to send to the template
			$story = [
				'title' => $title,
				'intro' => $intro,
				'img' => $img,
				'imgAlt' => $imgAlt,
				'content' => $content,
				'url' => "http://www.granma.cu/$query"
			];

			// create the cache
			file_put_contents($cache, serialize($story));
		}

		return $story;
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 * @return String
	 */
	private function urlSplit($url): string
	{
		$url = explode('/', trim($url));
		unset($url[0], $url[1], $url[2]);
		return implode('/', $url);
	}
}