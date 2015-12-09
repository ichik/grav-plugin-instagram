<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use Grav\Common\Page\Page;

class InstagramPlugin extends Plugin
{
    private $template_html = 'partials/instagram.html.twig';
    private $template_vars = [];

    /**
     * Return a list of subscribed events.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents() {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize configuration.
     */
    public function onPluginsInitialized()
    {
        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0]
        ]);
    }

    /**
     * Add Twig Extensions.
     */
    public function onTwigInitialized()
    {
        require_once __DIR__ . '/classes/Twig/InstagramTwigExtension.php';
        $this->grav['twig']->twig->addFunction(new \Twig_SimpleFunction('instagram_feed', [$this, 'getFeed']));
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * @return array
     */
    public function getFeed($params = [])
    {
        /** @var Page $page */
        $page = $this->grav['page'];
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        /** @var Data $config */
        $config = $this->mergeConfig($page, TRUE);

        // Fetch data from API
        $url = 'https://api.instagram.com/v1/users/' . $config->get('feed_parameters.user_id') .'/media/recent/?client_id=' . $config->get('feed_parameters.client_id');
        $data = $this->fetchData($url);
        $this->parseResponse($data);

        $this->template_vars = [
            'user_id'   => $config->get('feed_parameters.user_id'),
            'client_id' => $config->get('feed_parameters.client_id'),
            'feed'      => $this->feeds,
            'count'     => $config->get('feed_parameters.count')
        ];

        $output = $this->grav['twig']->twig()->render($this->template_html, $this->template_vars);

        return $output;
    }

    /**
     * Perform a curl request to the Instagram API
     * @param $url
     *
     * @return bool|mixed The response from the API
     */
    private function fetchData($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $content = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response_code == 200) {
            return $content;
        }
        return false;
    }

    private function addFeed($result) {
        foreach ($result as $key => $val) {
            if (!isset($this->feeds[$key])) {
                $this->feeds[$key] = $val;
            }
        }
        krsort($this->feeds);
    }

    private function parseResponse($json) {
        $r = array();
        $content = json_decode($json, true);
        if (count($content['data'])) {
            foreach ($content['data'] as $key => $val) {
                $created_at = $val['created_time'];
                $r[$created_at]['time'] = $created_at;
                $r[$created_at]['text'] = $val['caption']['text'];
                $r[$created_at]['image'] = $val['images']['standard_resolution']['url'];
                $r[$created_at]['thumb'] = $val['images']['low_resolution']['url'];
                $r[$created_at]['user'] = $val['user']['full_name'];
                $r[$created_at]['link'] = $val['link'];
            }
            $this->addFeed($r);
        }
    }
}