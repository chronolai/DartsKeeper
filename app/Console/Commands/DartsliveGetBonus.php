<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DartsliveGetBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dartslive:bonus {cart_id} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get bonus on dartslive';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        list($name, $need) = $this->getNameAndNeed();
        $coin = $this->getCoin();
        if ($need) {
            $this->getBonus();
            $coin = $coin . ' > '. $this->getCoin();
        }
        $this->line(sprintf("[%s] Coin: %s", $name, $coin));
    }

    public function getNameAndNeed()
    {
        $login_resp = $this->client->post('/t/doLogin.jsp', [
            'form_params' => [
                'i' => $this->argument('cart_id'),
                'p' => $this->argument('password'),
            ]
        ]);
        $login_page = $login_resp->getBody();

        preg_match_all("/<title>DARTSLIVE \[(.*)\]<\/title>/", $login_page, $name_match);
        preg_match_all("/(<div class=\"bonusGet\">GET!<\/div>)/", $login_page, $need_match);

        $name = $name_match[1][0];
        $need = count($need_match[1]) > 0;
        return [$name, $need];
    }

    public function getCoin()
    {
        $theme_resp = $this->client->get('/t/theme/theme_buy.jsp');
        $theme_page = $theme_resp->getBody();

        preg_match_all("/<div class=\"coinNumArea\"><span class=\"num\">(\d+)<\/span><span class=\"thisMonth\">\(.*\)<\/span><\/div>/", $theme_page, $coin_match);

        $coin = $coin_match[1][0];
        return $coin;
    }

    public function getBonus()
    {
        $bonus_resp = $this->client->get('/account/bonus/index.jsp');
    }
}
