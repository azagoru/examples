<?php

namespace App\Services;

use App\Product;
use App\Repositories\AgentGroupRepository;
use App\Repositories\AgentRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReleaseRepository;
use App\Repositories\SearchAgentsRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class SearchByAgentsService
{
    private $agent;
    private $release;
    private $product;
    private $category;
    private $agent_group;
    private $search_agents;

    public function __construct(
        AgentRepository $agent,
        ReleaseRepository $release,
        ProductRepository $product,
        CategoryRepository $category,
        AgentGroupRepository $agentGroup,
        SearchAgentsRepository $searchAgent
    )
    {
        $this->agent        = $agent;
        $this->release      = $release;
        $this->product      = $product;
        $this->category     = $category;
        $this->agent_group  = $agentGroup;
        $this->search_agents = $searchAgent;
    }

    public function get($isQuery, $searchCategories, $searchReleases, $searchAgentsData)
    {
        $categoryGroupList  = $this->category->getParents();
        $agentList          = $this->agent->getActive()->keyBy('id')->sortBy('title');
        $releaseList        = $this->release->getAll()->sortBy('title_short');
        $categoryList       = $this->category->getActive();
        $agentGroupList     = DB::table('agent_groups AS ag')
            ->select(DB::raw('count(a.id) as agents_count, ag.id, ag.title_short'))
            ->leftJoin('agents AS a', 'ag.id', '=', 'a.group_id')
            ->where('a.products_count', '>', 0)
            ->groupBy('ag.id', 'ag.title_short')
            ->orderBy('ag.title_short')
            ->get();

        $agentGroupList->transform(function($item)
        {
            $agents = DB::table('agents')
                ->select('id', 'title', 'products_count', 'min', 'max', 'group_id')
                ->where('products_count', '>', 0)
                ->where('group_id', $item->id)
                ->orderBy('title')
                ->get();

            if ($agents)
                $agents = $agents->keyBy('id');

            $item->agents = $agents;

            return $item;
        });

        $agentGroupList = $agentGroupList->keyBy('id');

        $categoryGroupList->transform(function($item) {
            $item->children = $this->category->getChildren($item->id)
                ->keyBy('id')
                ->sortBy('title');

            return $item;
        });

        $searchReleases     = $searchReleases   ? $searchReleases   : $releaseList->keyBy('id')->keys()->toArray();
        $searchCategories   = $searchCategories ? $searchCategories : $categoryList->keyBy('id')->keys()->toArray();

        $searchGroups = [];
        $searchAgents = [];
        foreach($searchAgentsData as $key => $row)
        {
            $row = explode(',', $row);

            if (count($row) === 3) {
                $agentId = (int) $row[0];
                if ($agentId && isset($agentList[$agentId])) {

                    $agent = $agentList[$agentId];

                    if ($agent->group)
                        $searchGroups[$agent->group->id] = 1;

                    $defMin = ($agent->min || ($agent->min == '0')) ? $agent->min : 0;
                    $defMax = ($agent->max || ($agent->max == '0')) ? $agent->max : 100;

                    $min = $row[1] ? $row[1] : $defMin;
                    $max = $row[2] ? $row[2] : $defMax;

                    $searchAgents[$agentId] = [$min, $max];
                }
            }
        }

        $searchAgentsPrepared = [];
        foreach($searchAgents as $agentId => $data)
        {
            if (!isset($searchAgentsPrepared[$agentList[$agentId]->group_id]))
                $searchAgentsPrepared[$agentList[$agentId]->group_id] = [];

            $searchAgentsPrepared[$agentList[$agentId]->group_id][$agentId] = $searchAgents[$agentId];
        }

        $searchGroups = array_keys($searchGroups);
        $products = [];

        if ($isQuery) {

            $query = null;
            $ids = [];
            if ($searchAgentsPrepared) {

                $query = DB::table('agent_product AS ap')
                    ->select('product_id', DB::raw('count(*) AS table_count'))
                    ->where(function($q) use ($searchAgentsPrepared) {
                        $agentsIds = [];
                        foreach($searchAgentsPrepared as $groupId => $agents) {
                            foreach ($agents as $agentId => $item)
                                $agentsIds[] = $agentId;

                            $q->orWhereIn('agent_id', $agentsIds);
                        }
                    })
                    ->groupBy('product_id');

                $res = $query->get();

                $count = count($searchAgentsPrepared);
                foreach ($res->all() as $item)
                    if ($item->table_count >= $count)
                        $ids[$item->product_id] = 1;

                $res = $this->product->getSome(array_keys($ids));

                $ids = [];
                foreach ($res as $key => $item)
                {
                    $in = 1;
                    foreach($searchGroups as $groupId)
                        if (!array_key_exists($groupId, $item->groups))
                            $in = 0;

                    if ($in)
                        $ids[] = $item->id;
                }
            }

            $query = Product::whereNull('parent_id')
                ->where('status', 1)
                ->whereIn('release_id', $searchReleases);

            if ($searchAgentsPrepared)
                $query->whereIn('id', $ids);

            $query->whereHas('categories', function($q) use($searchCategories) {
                $q->whereIn('category_id', $searchCategories);
            })->get();

            $query->whereNull('products.parent_id');
            $query->where('products.status', 1);
            $query->orderBy('products.title');

            $products = $query->get();

            $products = $products->filter(function($product) use($searchAgents/*, $mixes*/)
            {
                foreach($product->agents as $agent)
                {
                    if (array_key_exists($agent->id, $searchAgents)) {
                        $percentage = $agent->pivot->percentage;
                        $min = $searchAgents[$agent->id][0];
                        $max = $searchAgents[$agent->id][1];

                        if (($percentage < $min) || ($percentage > $max))
                            return false;
                    }
                }

                return $product;
            });

            $perPage = 20;

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $products->slice($perPage * ($currentPage - 1), $perPage);

            $products = new LengthAwarePaginator($currentItems, count($products), $perPage, $currentPage, [
                'path' => Paginator::resolveCurrentPath()
            ]);


            if ($products) {
                $agentsService = resolve(AgentsService::class);
                $products->transform(function ($item) use ($agentsService) {
                    $item->agents = $agentsService->render($item->agents, 1);

                    return $item;
                });
            }
        }

        $metaService = resolve(MetaService::class);
        $metaService->make('search_agents');

        $searchAgentsString = [];
        foreach($searchAgents as $agentId => $data)
            $searchAgentsString[] = implode(',', [$agentId, $data[0], $data[1]]);

        $searchAgentsString = implode('|', $searchAgentsString);
        $searchReleasesString = implode(',', $searchReleases);
        $searchCategoriesString = implode(',', $searchCategories);

        return view('search_agents',
            [
                'categoryGroupList'     => $categoryGroupList,
                'searchCategories'      => $searchCategories,

                'releaseList'           => $releaseList,
                'searchReleases'        => $searchReleases,

                'agentGroupList'        => $agentGroupList,
                'searchGroups'          => $searchGroups,
                'searchAgents'          => $searchAgents,

                'searchAgentsPrepared'  => $searchAgentsPrepared,

                'searchAgentsString'        => $searchAgentsString,
                'searchReleasesString'      => $searchReleasesString,
                'searchCategoriesString'    => $searchCategoriesString,
                'searchQuery'               => $isQuery,

                'products'              => $products,
            ]
        );
    }
}