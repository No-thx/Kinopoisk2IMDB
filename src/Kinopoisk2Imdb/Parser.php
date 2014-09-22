<?php
namespace Kinopoisk2Imdb;

use Kinopoisk2Imdb\Config\Config;

/**
 * Class Parser
 * @package Kinopoisk2Imdb
 */
class Parser
{
    /**
     * @var Filesystem
     */
    private $fs;

    /**
     *
     */
    public function __construct()
    {
        $this->fs = new Filesystem();
    }

    /**
     * @param $data
     * @return string
     */
    public function parseKinopoiskTable($data)
    {
        return $this->executeQuery(
            $data,
            "//table//tr",
            function ($query) {
                $data = [];
                $index = 0;

                foreach ($query as $tr) {
                    /** @var \DomDocument $tr */
                    foreach ($tr->getElementsByTagName('td') as $td) {
                        $data[$index][] = $td->nodeValue;
                    }
                    $index++;
                }

                return $data;
            }
        );
    }

    /**
     * @param string $data
     * @return bool|string
     */
    public function parseMovieId($data)
    {
        try {
            // Декодируем строку json в массив
            $data['json'] = $this->fs->setData($data['json'])->decodeJson()->getData();

            // Ищем и устанавливаем доступную категорию (чем выше в массиве - тем выше приоритет) и если не найдено - кидам Exception
            $categories = [
                'title_popular',
                'title_exact',
                'title_substring'
            ];

            foreach ($categories as $category) {
                if (isset($data['json'][$category])) {
                    $type = $category;
                    break;
                }
            }

            if (!isset($type)) {
                throw new \Exception('Пустые категории в результатах поиска');
            }

            // Ищем фильм и вовзращаем его ID, а если не найден - возвращаем false
            foreach ($data['json'][$type] as $movie) {
                if ($movie[Config::MOVIE_TITLE] === $data[Config::MOVIE_TITLE]) {
                    if (strpos($movie['title_description'], $data[Config::MOVIE_YEAR]) !== false) {
                        $movie_id = $movie['id'];
                        break;
                    }
                }
            }

            if (!isset($movie_id)) {
                return false;
            }

            return $movie_id;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param string $data
     * @return string
     */
    public function parseMovieAuthString($data)
    {
        return $this->executeQuery(
            $data,
            '//*[@data-auth]/@data-auth',
            function ($query) {
                $data = '';
                foreach ($query as $v) {
                    /** @var \DomDocument $v */
                    $node_value = $v->nodeValue;
                    if (!empty($node_value)) {
                        $data = $node_value;
                        break;
                    }
                }

                return $data;
            }
        );
    }

    /**
     * @param $data
     * @param bool $disable_errors
     * @return \DomXPath
     */
    public function loadDom($data, $disable_errors = true)
    {
        if ($disable_errors === true) {
            libxml_use_internal_errors(true);
        }

        $dom = new \DomDocument;
        $dom->loadHTML($data);
        $xpath = new \DomXPath($dom);

        if ($disable_errors === true) {
            libxml_clear_errors();
        }

        return $xpath;
    }

    /**
     * @param $data
     * @param $query
     * @param callable $callback
     * @return string
     */
    public function executeQuery($data, $query, \Closure $callback)
    {
        try {
            $query = $this->loadDom($data)->query($query);

            return $callback($query);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
