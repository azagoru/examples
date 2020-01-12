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
    private $agent;
    private $search;
    private $product;
    private $agent_group;
    private $manufacturer;

    private $exclude        = ['для'];
    private $maxWords       = 12;
    private $wordMinLength  = 3;

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

    public function get($term)
    {
        $products = DB::table('products_search')
            ->where('title', 'like', '%' . mb_strtolower($term) . '%')
            ->orderBy('title')
            ->get();

        $products = $products->sortBy(function($item) use ($term) {
            return mb_strpos(mb_strtolower($item->title), $term);
        });

        $products = $products->values()->slice(0, 8);

        return $products;
    }

    public function search($term)
    {
        $term = mb_strtolower($term);

        $terms[$term] = 1;

        $pattern = '/[\'\"\*\)\(«»]+/u';
        $termUpdated = preg_replace($pattern, '', $term);

        $pattern = '/[\-_]+/u';
        $termUpdated = preg_replace($pattern, ' ', $termUpdated);

        $words = explode(' ', $termUpdated);
        $words[] = $termUpdated;

        $counter = 0;
        foreach($words as $key => $word)
        {
            if ($counter == $this->maxWords)
                break;

            $word = trim($word);

            if (mb_strlen($word) < $this->wordMinLength) // исключаем слова длиной меньше 2 символов
                unset($words[$key]);
            else {
                if (!in_array($word, $this->exclude)) {
                    $words[$key] = $word;
                    $counter++;
                }
            }
        }

        $ids = [];
        foreach($words as $word)
        {
            $r = Redis::get($word);
            $terms[$word] = 1;

            if ($r) {
                $r = json_decode($r, true);
                foreach($r as $id)
                    isset($ids[$id])
                        ? $ids[$id]++
                        : $ids[$id] = 1;
            }
        }

        if (!$ids) {
            foreach($words as $word)
            {
                $res = DB::table('products')
                    ->select('id')
                    ->where('status', '>', 0)
                    ->whereNull('parent_id')
                    ->where('name', 'LIKE', '%' . $word . '%')
                    ->get();

                if ($res->count()) {
                    foreach ($res as $item)
                        isset($ids[$item->id])
                            ? $ids[$item->id]++
                            : $ids[$item->id] = 1;
                }
            }
        }

        if (!$ids) {
            $redisTerms = Redis::smembers('terms');

            foreach($words as $word) {
                foreach ($redisTerms as $redisTerm) {

                    $length = mb_strlen($word);

                    $maxErrors = 2;
                    if ($length < 6)
                        $maxErrors = ($length == 3) ? 0 : 1;

                    if ($maxErrors) {
                        if ($this->levenshtein_utf8($redisTerm, $word) < $maxErrors) {
                            $r = Redis::get($redisTerm);
                            $terms[$redisTerm] = 1;

                            if ($r) {
                                $r = json_decode($r, true);
                                foreach ($r as $id) {
                                    isset($ids[$id])
                                        ? $ids[$id]++
                                        : $ids[$id] = 1;
                                }
                            }
                        }
                    }
                }
            }
        }

        $terms = array_keys($terms);

        $result = [
            'ip'        => request()->ip(),
            'term'      => $term,
            'terms'     => $terms,
        ];

        if (!$ids) {

            foreach($words as $word)
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
                    $manufacturer = $this->manufacturer->get($id);
                    if ($manufacturer->active()->count()) {
                        $result['entity']       = 'manufacturer';
                        $result['ids']          = [$id];
                        $result['target_ids']   = [$id];

                        $this->search->insert($result);

                        return redirect()->route('manufacturer.view', $manufacturer->alias);
                    } else {
                        $result['entity']       = 'manufacturer';
                        $result['ids']          = [$id];
                        $result['target_ids']   = [];

                        return $this->product->getSome([]);
                    }
                }
            }
        }

        if (!$ids) {
            foreach($words as $word)
            {
                $agents = DB::table('agents')
                    ->select('id')
                    ->where('title', 'LIKE', '%' . $word . '%')
                    ->get();

                if ($agents->count()) {
                    $id = $agents->first()->id;

                    $result['entity']       = 'agent';
                    $result['ids']          = [$id];
                    $result['target_ids']   = [$id];

                    $this->search->insert($result);

                    return redirect()->route('product.search.agents', [
                        'query' => 1,
                        'agents' => implode(',', [$id, 0, 100])
                    ]);
                }
            }
        }

        if (!$ids) {
            foreach($words as $word)
            {
                $groups = DB::table('agent_groups')
                    ->select('id')
                    ->where('title', 'LIKE', '%' . $word . '%')
                    ->get();

                if ($groups->count()) {
                    $id = $groups->first()->id;

                    $result['entity']       = 'agent_group';
                    $result['ids']          = [$id];
                    $result['target_ids']   = [$id];

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
        }

        $productIds = array_keys($ids);

        $products = $this->product->getSome($productIds);
        $products = $products->sortByDesc(function($item) use ($ids) {
            return $ids[$item->id];
        });

        View::share('terms', $terms);

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

        $result['entity']       = 'product';
        $result['ids']          = $productIds;
        $result['target_ids']   = $targetIds;

        $this->search->insert($result);

        return $products;
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