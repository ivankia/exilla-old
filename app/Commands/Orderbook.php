<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class Orderbook extends Command
{
    /**
     * The signature of the command.
     * ./exilla orderbook:get --pf=8000 --pt=11500 --s=25 --l1=50 --l2=100 --l3=250
     * @var string
     */
    protected $signature = 'orderbook:get {--pf=7000} {--pt=20000} {--s=50} {--schema=discrete_levels} {--l1=100} {--l2=200} {--l3=300}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Get OrderBook';

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var float
     */
    protected $lastPrice;


    protected $instrument;
    /**
     * @var array
     */
    protected $notifications;

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    /**
     * Orderbook constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->loadParams();
        $this->loadInstrument();
    }

    /**
     * @return bool
     */
    public function handle()
    {
        $this->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->setStyles();
        $this->processOptions();

        while (1) {
            $this->loadInstrument();
            $this->observe();
            sleep(2);
        }
    }

    protected function processOptions()
    {
        if ($this->option('pf') && $this->option('pt')) {
            $this->setParam('price_limits', [$this->option('pf'), $this->option('pt')]);
        }

        if ($this->option('s')) {
            $this->setParam('min_size', $this->option('s'));
        }

        if ($this->option('schema')) {
            $this->setParam('schema', $this->option('schema'));
        }

        if ($this->option('l1') && $this->option('l2') && $this->option('l3')) {
            $this->setParam('discrete_levels', [
                'low'  => $this->option('l1'),
                'mid'  => $this->option('l2'),
                'high' => $this->option('l3'),
            ]);
        }
    }

    protected function observe()
    {
        $buys  = [];
        $sells = [];

        $data = json_decode(file_get_contents($this->getAPIUrl()), JSON_OBJECT_AS_ARRAY, 2147483646);

        $prevLastPrice = $this->getLastPrice();

        $this->setLastPrice($this->getInstrument('lastPrice'));

        foreach ($data as $key => $val) {
            $order = [
                'price' => $val[0]['price'],
            ];

            $amount = $val[0]['size'];
            $size   = $amount / $order['price'];

            if ($order['price'] > $this->getParam('price_limits')[0]
                && $order['price'] < $this->getParam('price_limits')[1]
                && $size >= $this->getParam('min_size')
            ) {
                $order['size']  = round($val[0]['size'] / $order['price'], 1);

                if ($val[0]['side'] == 'Buy') {
                    $buys[] = $order;
                } else {
                    $sells[] = $order;
                }
            }
        }

        $this->pushNotification('Buy/Sell Size-Price | <fg=white;bg=' . $this->getPriceBackgroundColor($prevLastPrice, $this->getLastPrice()) .'> $ ' . $this->getLastPrice() . ' </> [' . join('-', $this->getParam('price_limits'))  . '] | V ' . $this->getParam('min_size'));
        $this->reportVr($buys, $sells);

        return true;
    }

    /**
     * @return array
     */
    protected function popNotification()
    {
        $notifications = $this->notifications;
        $this->notifications  = [];

        return $notifications;
    }

    /**
     * @param string $msg
     */
    protected function pushNotification($msg)
    {
        $this->notifications[] = $msg;
    }

    public function getPriceBackgroundColor($priceBefore, $priceNow)
    {
        $bgPrice = 'blue';

        if ($priceBefore < $priceNow) {
            $bgPrice = 'green';
        } elseif ($priceBefore > $priceNow) {
            $bgPrice = 'red';
        }

        return $bgPrice;
    }

    /**
     * @param float $price
     */
    protected function setLastPrice($price)
    {
        $this->lastPrice = $price;
    }

    /**
     * @return float
     */
    protected function getLastPrice()
    {
        return $this->lastPrice;
    }

    /**
     * @param string $key
     * @return array|float
     */
    protected function getInstrument($key = null)
    {
        return !$key ? $this->instrument : $this->instrument[$key];

    }

    protected function loadInstrument()
    {
        $data = json_decode(file_get_contents($this->getParam('bitmex')['api_url_instrument'] . $this->getParam('bitmex')['symbol']), JSON_OBJECT_AS_ARRAY, 2147483646);

        $this->instrument = $data[0][0];
    }

    /**
     * @param array $orderbook
     * @param string $param
     * @param int $limit
     * @param int $sort
     * @param array $headers
     * @return void
     */
    protected function reportHr($orderbook, $param = 'size', $limit = 30, $sort = SORT_DESC, $headers = ['Price', 'Size'])
    {
        $limit--;

        if ($param == 'size' || $param == 'price') {
            array_multisort(array_column($orderbook, $param), $sort, $orderbook);
        }

        if ($param == 'size_price') {
            array_multisort(array_column($orderbook, 'size'), $sort, $orderbook);

            $orderbook = array_slice($orderbook, 0, $limit);

            usort($orderbook, function($a, $b) {
                return $b['price'] <=> $a['price'];
            });
        }

        if ($param == 'price_size') {
            array_multisort(array_column($orderbook, 'price'), $sort, $orderbook);

            $orderbook = array_slice($orderbook, 0, $limit);

            usort($orderbook, function($a, $b) {
                return $b['size'] <=> $a['size'];
            });
        }

        $this->table($headers, array_slice($orderbook, 0, $limit));
    }

    /**
     * @param array $orderbookL
     * @param array $orderbookR
     * @param array $params
     * @return void
     */
    protected function reportVr($orderbookL, $orderbookR, $params = [])
    {
        $params = array_merge($this->getParams(), $params);    

        $params['limit']--;

        if ($params['column'] == 'size' || $params['column'] == 'price') {
            array_multisort(array_column($orderbookL, $params['column']), $params['sortL'], $orderbookL);
            array_multisort(array_column($orderbookR, $params['column']), $params['sortR'], $orderbookR);

            $orderbookL = array_slice($orderbookL, 0, $params['limit']);
            $orderbookR = array_slice($orderbookR, 0, $params['limit']);
        }

        if ($params['column'] == 'size_price') {
            array_multisort(array_column($orderbookL, 'size'), $params['sortL'], $orderbookL);

            $orderbookL = array_slice($orderbookL, 0, $params['limit']);

            usort($orderbookL, function($a, $b) {
                return $b['price'] <=> $a['price'];
            });

            array_multisort(array_column($orderbookR, 'size'), $params['sortR'], $orderbookR);

            $orderbookR = array_slice($orderbookR, 0, $params['limit']);

            usort($orderbookR, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });

            if ($this->getParam('schema') == 'quoter_average') {
                $orderbookL = $this->colorFillQuoterAverage($orderbookL, 'size', $this->analyse($orderbookL));
                $orderbookR = $this->colorFillQuoterAverage($orderbookR, 'size', $this->analyse($orderbookR));
            } elseif ($this->getParam('schema') == 'discrete_levels') {
                $orderbookL = $this->colorFillDiscreteLevels($orderbookL, 'size');
                $orderbookR = $this->colorFillDiscreteLevels($orderbookR, 'size');
            }
        }

        if ($params['column'] == 'price_size') {
            array_multisort(array_column($orderbookL, 'price'), $params['sortL'], $orderbookL);

            $orderbookL = array_slice($orderbookL, 0, $params['limit']);

            usort($orderbookL, function($a, $b) {
                return $b['size'] <=> $a['size'];
            });

            array_multisort(array_column($orderbookR, 'price'), $params['sortR'], $orderbookR);

            $orderbookR = array_slice($orderbookR, 0, $params['limit']);

            usort($orderbookR, function($a, $b) {
                return $a['size'] <=> $b['size'];
            });

            if ($this->getParam('schema') == 'quoter_average') {
                $orderbookL = $this->colorFillQuoterAverage($orderbookL, 'price', $this->analyse($orderbookL));
                $orderbookR = $this->colorFillQuoterAverage($orderbookR, 'price', $this->analyse($orderbookR));
            } elseif ($this->getParam('schema') == 'discrete_levels') {
                $orderbookL = $this->colorFillDiscreteLevels($orderbookL, 'price');
                $orderbookR = $this->colorFillDiscreteLevels($orderbookR, 'price');
            }
        }

        $orderbook  = $this->mergeBuySellOrderbook($orderbookL, $orderbookR);

        system('clear');

        $notifications = $this->popNotification();

        if (count($notifications)) {
            foreach ($notifications as $notification) {
                $this->getOutput()->section($notification);
            }
        }

        $this->table($this->getParam('headers'), $orderbook);
    }

    /**
     * @param array $b
     * @param array $s
     * @return array
     */
    protected function mergeBuySellOrderbook($b, $s)
    {
        $bP = array_column($b, 'price');
        $sP = array_column($s, 'price');
        $bS = array_column($b, 'size');
        $sS = array_column($s, 'size');

        $orderbook = array_keys($bP);

        foreach ($orderbook as $key => $val) {
            $orderbook[$key] = [
                'BUY_SIZE' => isset($bS[$key]) ? $bS[$key] : '',
                'BUY_PRICE' => isset($bP[$key]) ? $bP[$key] : '',
                'DELIMITER' => '  ',
                'SELL_PRICE' => isset($sP[$key]) ? $sP[$key] : '',
                'SELL_SIZE' => isset($sS[$key]) ? $sS[$key] : '',
            ];
        }

        return $orderbook;
    }

    /**
     * @param array $orderbook
     * @return array
     */
    protected function analyse($orderbook)
    {
        $sizes  = array_column($orderbook, 'size');
        $prices = array_column($orderbook, 'price');

        $maxminS = ['max' => max($sizes), 'min' => min($sizes)];
        $maxminP = ['max' => max($prices), 'min' => min($prices)];

        $cnt    = count($sizes);
        $quater = $cnt / 4;

        $q1 = $quater - 1;
        $q2 = $cnt - $q1;

        $avgS = array_sum($sizes) / count($sizes);
        $avgP = array_sum($prices) / count($prices);

        sort($sizes, SORT_DESC);
        sort($prices, SORT_DESC);

        $q1AvgS = array_sum(array_slice($sizes, 0, $q1)) / $quater;
        $q2AvgS = array_sum(array_slice($sizes,  $q2, $cnt)) / $quater;
        $q1AvgP = array_sum(array_slice($prices, 0, $q1)) / $quater;
        $q2AvgP = array_sum(array_slice($prices,  $q2, $cnt)) / $quater;

        return [
            'size'   => $maxminS,
            'price'  => $maxminP,
            'avgp'   => $avgP,
            'avgs'   => $avgS,
            'q1avgs' => $q1AvgS,
            'q1avgp' => $q1AvgP,
            'q2avgs' => $q2AvgS,
            'q2avgp' => $q2AvgP,
        ];
    }

    /**
     * @param array $orderbook
     * @param string $param
     * @param array $deps
     * @return array
     */
    protected function colorFillQuoterAverage($orderbook, $param, $deps)
    {
        foreach ($orderbook as $key => $val) {
            if ($param == 'size' && $val['size'] >= $deps['q2avgs']) {
                $orderbook[$key]['size']  = $this->h1($val['size']);
                $orderbook[$key]['price'] = $this->h2($val['price']);
            }

            if ($param == 'price' && $val['price'] >= $deps['q2avgp']) {
                $orderbook[$key]['price'] = $this->h1($val['price']);
                $orderbook[$key]['size']  = $this->h2($val['size']);
            }
        }

        return $orderbook;
    }

    protected function colorFillDiscreteLevels($orderbook, $param)
    {
        $levels = $this->getParam('discrete_levels');

        foreach ($orderbook as $key => $val) {
            foreach ($levels as $levelCode => $level) {
                if ($param == 'size' && $val['size'] >= $level) {
                    if ($levelCode == 'low') {
                        $orderbook[$key]['size'] = $this->h100($val['size']);
                        $orderbook[$key]['price'] = $this->h101($val['price']);
                    } elseif ($levelCode == 'mid') {
                        $orderbook[$key]['size'] = $this->h200($val['size']);
                        $orderbook[$key]['price'] = $this->h201($val['price']);
                    } elseif ($levelCode == 'high') {
                        $orderbook[$key]['size'] = $this->h300($val['size']);
                        $orderbook[$key]['price'] = $this->h301($val['price']);
                    }
                } else {
                    //
                }

                if ($param == 'price' && $val['price'] >= $level) {
                    if ($levelCode == 'low') {
                        $orderbook[$key]['price'] = $this->h100($val['price']);
                        $orderbook[$key]['size'] = $this->h101($val['size']);
                    } elseif ($levelCode == 'mid') {
                        $orderbook[$key]['price'] = $this->h200($val['price']);
                        $orderbook[$key]['size'] = $this->h201($val['size']);
                    } elseif ($levelCode == 'high') {
                        $orderbook[$key]['price'] = $this->h300($val['price']);
                        $orderbook[$key]['size'] = $this->h301($val['size']);
                    }
                } else {
                    //
                }
            }
        }

        return $orderbook;
    }

    /**
     * @return array $params
     */
    protected function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $name
     * @return mixed
     */
    protected function getParam($name)
    {
        return $this->params[$name];
    }

    /**
     * @param array $params
     */
    protected function setParams($params)
    {
        $this->params = $params;
    }


    protected function setParam($name, $value)
    {
        $this->params[$name] = $value;
    }

    protected function loadParams()
    {
        $this->setParams(config('app.orderbook_params'));
    }

    protected function getAPIUrl() {
        return $this->getParam($this->getParam('exchange'))['api_url'] . $this->getParam($this->getParam('exchange'))['depth'];
    }

    /**
     * Colors: black, red, green, yellow, blue, magenta, cyan, white, default
     * @return void
     */
    protected function setStyles()
    {
        $this->getOutput()->getFormatter()->setStyle(
            'h1',
            new OutputFormatterStyle('red', 'white', ['bold'])
        );
        $this->getOutput()->getFormatter()->setStyle(
            'h2',
            new OutputFormatterStyle('red', 'default', ['bold'])
        );

        // low
        $this->getOutput()->getFormatter()->setStyle(
            'h100',
            new OutputFormatterStyle('green', 'default', ['bold'])
        );
        $this->getOutput()->getFormatter()->setStyle(
            'h101',
            new OutputFormatterStyle('green', 'default')
        );
        //mid
        $this->getOutput()->getFormatter()->setStyle(
            'h200',
            new OutputFormatterStyle('yellow', 'default', ['bold'])
        );
        $this->getOutput()->getFormatter()->setStyle(
            'h201',
            new OutputFormatterStyle('yellow', 'default')
        );
        // high
        $this->getOutput()->getFormatter()->setStyle(
            'h300',
            new OutputFormatterStyle('white', 'red', ['bold'])
        );
        $this->getOutput()->getFormatter()->setStyle(
            'h301',
            new OutputFormatterStyle('red', 'default', ['bold'])
        );
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h1($string)
    {
        return '<h1>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h2($string)
    {
        return '<h2>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h100($string)
    {
        return '<h100>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h101($string)
    {
        return '<h101>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h200($string)
    {
        return '<h200>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h201($string)
    {
        return '<h201>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h300($string)
    {
        return '<h300>' . $string . '</>';
    }

    /**
     * @param string $string
     * @return string
     */
    protected function h301($string)
    {
        return '<h301>' . $string . '</>';
    }
}
