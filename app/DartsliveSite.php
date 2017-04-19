<?php

namespace App;

class DartsliveSite
{
    private $patterns;
    private $jar;
    private $client;

    public function __construct()
    {
        $this->card_id = '';
        $this->password = '';
        $this->name = '';
        $this->coin = 0;
        $this->expire = 0;
        $this->bonus = 0;
        $this->need = false;
        $this->rating = 0;

        $this->patterns = [
            'rating' => '/\<li id=\"btn_rt_(\d+)\"\>/',
            'name' => '/<title>DARTSLIVE \[(.*)\]<\/title>/',
            'need' => '/(<div class=\"bonusGet\">GET!<\/div>)/',
            'coin' => '/<div class=\"coinNumArea\"><span class=\"num\">(?<coin>\d+)<\/span><span class=\"thisMonth\">\((?<expire>\d+)\)<\/span><\/div>/',
            'bonus' => '/\<span class=\"coinNum\"\>(\d+)\<\/span\>/',
        ];

        $this->jar = new \GuzzleHttp\Cookie\CookieJar();
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => 'https://card.dartslive.com',
            'cookies' => $this->jar,
            'headers' => [
                'Host' =>'card.dartslive.com',
                'Origin' =>'https://card.dartslive.com',
                'Referer' =>'https://card.dartslive.com/t/login.jsp',
                'User-Agent' =>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.100 Safari/537.36',
            ],
        ]);
    }

    public function login($card_id, $password)
    {
        $resp = $this->client->post('/t/doLogin.jsp', [
            'form_params' => [
                'i' => $card_id,
                'p' => $password,
            ]
        ]);
        $page = $resp->getBody();

        preg_match_all($this->patterns['rating'], $page, $rating_match);
        preg_match_all($this->patterns['name'], $page, $name_match);
        preg_match_all($this->patterns['need'], $page, $need_match);

        if (count($name_match[1]) === 0) {
            return false;
        }

        $this->card_id = $card_id;
        $this->password = $password;
        $this->rating = $rating_match[1][0];
        $this->name = $name_match[1][0];
        $this->need = count($need_match[1]) > 0;
        $this->getCoin();
        return true;
    }

    public function getCoin()
    {
        $resp = $this->client->get('/t/theme/theme_buy.jsp');
        $page = $resp->getBody();

        preg_match_all($this->patterns['coin'], $page, $coin_match);

        $this->coin = $coin_match['coin'][0];
        $this->expire = $coin_match['expire'][0];
        return $this->coin;
    }

    public function getBonus()
    {
        $resp = $this->client->get('/account/bonus/index.jsp');
        $page = $resp->getBody();

        preg_match_all($this->patterns['bonus'], $page, $bonus_match);

        if (count($bonus_match[1]) > 0) {
            $this->bonus = $bonus_match[1][0];
        }
        return $this->bonus;
    }
}
