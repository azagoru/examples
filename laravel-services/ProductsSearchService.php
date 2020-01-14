<?php

namespace App\Services;

use App\Repositories\AgentGroupRepository;
use App\Repositories\AgentRepository;
use App\Repositories\ManufacturerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SearchRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\View;

class ProductsSearchService
{
    public $term = '';
    public $words = [];
    public $terms = [];

    private $exclude        = ['для', 'или'];
    private $wordsLimit     = 6;
    private $wordMinLength  = 3;

    private $agent;
    private $search;
    private $product;
    private $agent_group;
    private $manufacturer;

    public function __construct(
        AgentRepository $agent,
        SearchRepository $search,
        ProductRepository $product,
        AgentGroupRepository $agentGroup,
        ManufacturerRepository $manufacturer
    )
    {
        $this->agent = $agent;
        $this->search = $search;
        $this->product = $product;
        $this->agent_group = $agentGroup;
        $this->manufacturer = $manufacturer;
    }

    public function search($term)
    {
        $this->setSearchWords($term);

        $result = $this->searchProducts();
        if ($result)
            return $result;

        $result = $this->searchManufacturer();
        if ($result)
            return $result;

        // todo добавить категории?

        $result = $this->searchAgent();
        if ($result)
            return $result;

        $result = $this->searchAgentGroup();
        if ($result)
            return $result;

        return false;
    }

    public function searchProducts()
    {
        $ids = [];
        foreach($this->words as $word) // ищем прямые соответствия
        {
            $r = Redis::get($word);
            if ($r) {
                $r = json_decode($r, true);
                foreach($r as $id)
                    isset($ids[$id]) ? $ids[$id]++ : $ids[$id] = 1;
            }
        }

        if (!$ids) {
            foreach($this->words as $word) // ищем частичные соответствия в названиях
            {
                $res = DB::table('products')
                    ->select('id')
                    ->where('status', '>', 0)
                    ->whereNull('parent_id')
                    ->where('name', 'LIKE', '%' . $word . '%')
                    ->get();

                if ($res->count()) {
                    $res = $res->keyBy('id');
                    foreach ($res as $id => $item)
                        isset($ids[$id]) ? $ids[$id]++ : $ids[$id] = 1;
                }
            }
        }

        if (!$ids) {
            $redisTerms = Redis::smembers('terms');
            foreach($this->words as $word) { // проверяем на ошибки в словах
                foreach ($redisTerms as $redisTerm) {

                    $length = mb_strlen($word);

                    $maxErrors = 2;
                    if ($length < 6)
                        $maxErrors = ($length == 3) ? 0 : 1;

                    if ($maxErrors) {
                        if ($this->levenshtein_utf8($redisTerm, $word) < $maxErrors) {
                            $r = Redis::get($redisTerm);
                            if ($r) {
                                $r = json_decode($r, true);
                                foreach ($r as $id)
                                    isset($ids[$id]) ? $ids[$id]++ : $ids[$id] = 1;
                            }

                            $this->terms[$redisTerm] = 1;
                        }
                    }
                }
            }
        }

        if (!$ids)
            return false;

        $productIds = array_keys($ids);

        $products = $this->product->getSome($productIds);
        $products = $products->sortByDesc(function($item) use ($ids) {
            return $ids[$item->id];
        });

        $targetIds = [];
        if (count($ids) > 1) {
            $max = max($ids);
            foreach ($ids as $id => $count)
                if ($count === $max)
                    $targetIds[] = $id;

            if (count($targetIds) === count($ids))
                $targetIds = [];
        } else {
            $targetIds = $productIds;
        }

        $this->terms = array_keys($this->terms);

        View::share('terms', $this->terms);

        $result = [
            'ip'            => request()->ip(),
            'terms'         => $this->terms,
            'entity'        => 'product',
            'ids'           => $productIds,
            'target_ids'    => $targetIds,
            'term'          => $this->term
        ];

        $this->search->insert($result);

        return $products;
    }

    public function searchManufacturer()
    {
        foreach($this->words as $word)
        {
            $manufacturers = DB::table('manufacturers')
                ->select('id')
                ->where('status', '>', 0)
                ->where(function($q) use ($word) {
                    $q->orWhere('title', 'LIKE', '%' . $word . '%');
                    $q->orWhere('title_lat', 'LIKE', '%' . $word . '%');
                })
                ->get();

            if ($manufacturers->count()) {
                $id = $manufacturers->first()->id;
                $manufacturer = $this->manufacturer->get($id); // Eloquent
                if ($manufacturer->active()->count()) { // if has active products

                    $result = [
                        'ip'            => request()->ip(),
                        'terms'         => array_keys($this->terms),
                        'entity'        => 'manufacturer',
                        'ids'           => [$id],
                        'target_ids'    => [$id],
                        'term'          => $this->term
                    ];

                    $this->search->insert($result);

                    return redirect()->route('manufacturer.view', $manufacturer->alias);
                }
            }
        }

        return false;
    }

    public function searchAgent()
    {
        foreach($this->words as $word)
        {
            $agents = DB::table('agents')
                ->select('id')
                ->where('title', 'LIKE', '%' . $word . '%')
                ->get();

            if ($agents->count()) {
                $id = $agents->first()->id;

                $result = [
                    'ip'            => request()->ip(),
                    'terms'         => array_keys($this->terms),
                    'entity'        => 'agent',
                    'ids'           => [$id],
                    'target_ids'    => [$id],
                    'term'          => $this->term
                ];

                $this->search->insert($result);

                return redirect()->route('product.search.agents', [
                    'query' => 1,
                    'agents' => implode(',', [$id, 0, 100])
                ]);
            }
        }

        return false;
    }

    public function searchAgentGroup()
    {
        foreach($this->words as $word)
        {
            $groups = DB::table('agent_groups')
                ->select('id')
                ->where('title', 'LIKE', '%' . $word . '%')
                ->get();

            if ($groups->count()) {
                $id = $groups->first()->id;

                $result = [
                    'ip'            => request()->ip(),
                    'terms'         => array_keys($this->terms),
                    'entity'        => 'agent_group',
                    'ids'           => [$id],
                    'target_ids'    => [$id],
                    'term'          => $this->term
                ];

                $this->search->insert($result);

                $agents = $this->agent->getSomeBy('group_id', $id);

                $contents = [];
                foreach($agents as $agent)
                    $contents[] = implode(',', [$agent->id, 0, 100]) ;

                return redirect()->route('product.search.agents', [
                    'query' => 1,
                    'agents' => implode('|', $contents)
                ]);
            }
        }

        return false;
    }

    private function setSearchWords($term)
    {
        $this->term = $term;

        $term = mb_strtolower($term);

        $this->terms[$term] = 1;

        $termUpdated = preg_replace('/[\'\"\*\)\(«»]+/u', '', $term);
        $termUpdated = preg_replace('/[\-\.\,\;\:_]+/u', ' ', $termUpdated);

        $wordsRaw = explode(' ', $termUpdated);
        $wordsRaw[] = $termUpdated;

        $counter = 0;
        foreach($wordsRaw as $key => $word)
        {
            if (mb_strlen($word) < $this->wordMinLength) // исключаем слова малой длины
                continue;

            if (in_array($word, $this->exclude))
                continue;

            $this->words[$word] = 1;
            $this->terms[$word] = 1;

            $counter++;
            if ($counter == $this->wordsLimit)
                break;
        }

        $this->words = array_keys($this->words);
    }

    private function levenshtein_utf8($s1, $s2)
    {
        $charMap = array();
        $s1 = $this->utf8_to_extended_ascii($s1, $charMap);
        $s2 = $this->utf8_to_extended_ascii($s2, $charMap);

        return levenshtein($s1, $s2);
    }

    private function utf8_to_extended_ascii($str, &$map)
    {
        // find all multibyte characters (cf. utf-8 encoding specs)
        $matches = array();
        if (!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches))
            return $str; // plain ascii string

        // update the encoding map with the characters not already met
        foreach ($matches[0] as $mbc)
            if (!isset($map[$mbc]))
                $map[$mbc] = chr(128 + count($map));

        // finally remap non-ascii characters
        return strtr($str, $map);
    }
}
