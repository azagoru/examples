<!-- Filter search component -->
<!-- https://orion-med.ru/product/search/agents -->
<template>
    <div class="bg-light py-2 px-2 search-agents-container">
        <form
                ref="form"
                @submit.prevent="collectData()"
                :action="this.product_search_agents_endpoint"
                method="GET"
                enctype="multipart/form-data">
            <div class="form-row">

                <div class="col-lg-3">
                    <div class="form-row">

                        <div class="col-12 mb-1">
                            <p class="search-block-title">Категории</p>
                            <div class="list-block" id="list_categories" data-simplebar data-simplebar-auto-hide="false">
                                <ul class="list-unstyled mb-0 bg-white">
                                    <li
                                            v-for="group in categoryGroupList"
                                            :class="{ parent: 1, active: categories[group.id].checked }">
                                        <input
                                                @change="updateCategories($event)"
                                                :value="group.id"
                                                :id="'category_group-' + group.id"
                                                :checked="categories[group.id].checked"
                                                type="checkbox">
                                        <label
                                                :for="'category_group-' + group.id"
                                                class="title">
                                            <span
                                                    v-html="group.title"
                                                    class="group-title"></span>
                                        </label>
                                        <ul class="list-unstyled ml-3">
                                            <li
                                                    v-for="child in group.children"
                                                    :class="{ child: 1, active: categories[child.id].checked }">
                                                <input
                                                        @change="updateCategories($event)"
                                                        :value="child.id"
                                                        :id="'category-' + child.id"
                                                        :checked="categories[child.id].checked"
                                                        type="checkbox">
                                                <label
                                                        :for="'category-' + child.id"
                                                        class="title"
                                                        v-html="child.title"></label>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>

                            <div class="p-1 d-flex justify-content-between">
                                <a href="#" class="pseudo" @click.prevent="toggleCategories(true)">Выбрать все</a>
                                <a href="#" class="pseudo" @click.prevent="toggleCategories(false)">Очистить</a>
                            </div>
                        </div>

                        <div class="col-12">
                            <p class="search-block-title">Форма выпуска</p>
                            <div class="list-block" id="list_releases" data-simplebar data-simplebar-auto-hide="false">
                                <ul class="list-unstyled mb-0 bg-white">
                                    <li
                                            v-for="release in releaseList"
                                            :class="{ parent: 1, active: releases[release.id] }">
                                        <input
                                                :checked="releases[release.id]"
                                                @change="updateReleases($event)"
                                                :value="release.id"
                                                :id="'release-' + release.id"
                                                type="checkbox">
                                        <label
                                                :for="'release-' + release.id"
                                                class="title"
                                                v-html="release.title"></label>
                                    </li>
                                </ul>
                            </div>

                            <div class="p-1 d-flex justify-content-between">
                                <a href="#" class="pseudo" @click.prevent="toggleReleases(true)">Выбрать все</a>
                                <a href="#" class="pseudo" @click.prevent="toggleReleases(false)">Очистить</a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="form-row">
                        <div class="col-12">

                            <div id="agents">
                                <p class="search-block-title">Действующие вещества (ДВ)</p>
                                <input
                                        type="text"
                                        class="search"
                                        placeholder="Поиск по названию ДВ"
                                        @keyup="searchAgent($event)"/>
                                <div class="list-block" id="list_agents" data-simplebar data-simplebar-auto-hide="false">
                                    <ul class="list-unstyled mb-0 bg-white">

                                        <li
                                                v-for="group in agentGroupList"
                                                :class="{ parent: 1, active: groups[group.id].checked } ">
                                            <div>
                                                <input
                                                        @change="updateAgents($event, 'group')"
                                                        :checked="groups[group.id].checked"
                                                        :id="'group-' + group.id"
                                                        :value="group.id"
                                                        type="checkbox">
                                                <label
                                                        :class="{ found: searchResult[group.id] }"
                                                        :for="'group-' + group.id"
                                                        v-html="group.title_short"></label>
                                            </div>
                                            <ul class="list-unstyled">
                                                <li
                                                        v-for="agent in group.agents"
                                                        :class="{
                                                            child: 1,
                                                            'pl-3': 1,
                                                            active: agents[agent.id].checked,
                                                            'd-none': !groups[agent.group_id].checked && !(searchResult[group.id] && (searchResult[group.id].indexOf(agent.id) > -1)),

                                                             }" >
                                                    <input
                                                            @change="updateAgents($event, 'agent')"
                                                            :checked="agents[agent.id].checked"
                                                            :id="'agent-' + agent.id"
                                                            :value="agent.id"
                                                            type="checkbox">
                                                    <label
                                                            :class="{ found: searchResult[group.id] && (searchResult[group.id].indexOf(agent.id) > -1) }"
                                                            :for="'agent-' + agent.id">
                                                        {{ agent.title }} (<span class="products-count">{{ agent.products_count }}</span>)
                                                    </label>
                                                </li>
                                            </ul>
                                        </li>

                                    </ul>
                                </div>

                                <div class="p-1 d-flex justify-content-end">
                                    <a href="#" class="pseudo" @click.prevent="toggleAgents(false)">Очистить</a>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="form-row mt-1">
                        <div class="col-12">
                            <div id="description" class="px-1 pb-1 bg-white" v-if="searchAgentsText">
                                <div>Дезсредство содержит:</div>
                                <ul class="mb-0">
                                    <li v-for="text in searchAgentsText" v-html="text">{{ text }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-row">

                        <div class="col-12 contents-container" v-show="agents">
                            <p class="search-block-title">% содержание ДВ</p>
                            <div class="contents-table-container list-block" data-simplebar data-simplebar-auto-hide="false">
                                <div id="contents_list">

                                    <table class="table table-borderless mb-0" v-for="(groupAgents, group_id) in searchAgentsPrepared">
                                        <thead>
                                        <tr>
                                            <th class="text-center p-2">
                                                <span class="search-agent-title" v-html="agentGroupList[group_id].title_short"></span>
                                            </th>
                                            <th>
                                                <input
                                                        type="checkbox"
                                                        @click.prevent="updateAgents($event, 'group')"
                                                        :value="group_id"
                                                        :checked="!!groups[group_id].checked" />
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(agentRange, agent_id) in groupAgents">
                                            <td>
                                                <span class="search-agent-title" v-html="agentGroupList[group_id]['agents'][agent_id].title"></span>
                                                <div class="d-flex">
                                                    <div>
                                                        От&nbsp;<input
                                                            :readonly="agentRange[0] === agentRange[1]"
                                                            v-model="agentRange[0]"
                                                            type="text"
                                                            :class="{ from: 1, error: errors[agent_id]['min'] }"
                                                            @keyup="validateAgentValue($event, agent_id, 'min')"
                                                    />&nbsp;до&nbsp;<input
                                                            :readonly="agentRange[0] === agentRange[1]"
                                                            v-model="agentRange[1]"
                                                            type="text"
                                                            :class="{ to: 1, error: errors[agent_id]['max'] }"
                                                            @keyup="validateAgentValue($event, agent_id, 'max')"
                                                    />&nbsp;%
                                                    </div>
                                                    <div v-if="agentRange[0] !== agentRange[1]">
                                                        <a href="#" class="reset pseudo" @click.prevent="resetAgentValues(agent_id, group_id)">Сбросить</a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input
                                                        type="checkbox"
                                                        :value="agent_id"
                                                        :checked="!!agents[agent_id].checked"
                                                        @click.prevent="updateAgents($event, 'agent')" />
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>

                        <div class="col-12 text-center mt-4">

                            <input type="hidden" name="query"       value="1" />
                            <input type="hidden" name="releases"    :value="releasesString" />
                            <input type="hidden" name="categories"  :value="categoriesString" />
                            <input type="hidden" name="agents"      :value="agentsString" />

                            <button class="btn btn-lg" type="submit" v-html="buttonText"></button>
                        </div>

                    </div>
                </div>

            </div>
        </form>
    </div>
</template>

<script>
    export default
    {
        data ()
        {
            let categoryGroupList       = JSON.parse(this.category_group_list),
                searchCategories        = JSON.parse(this.search_categories),
                releaseList             = JSON.parse(this.release_list),
                searchReleases          = JSON.parse(this.search_releases),

                agentGroupList          = JSON.parse(this.agent_group_list),
                searchAgents            = JSON.parse(this.search_agents),
                searchGroups            = JSON.parse(this.search_groups),

                searchAgentsPrepared    = JSON.parse(this.search_agents_prepared);

            let key;

            let groups = {},
                agents = {},
                errors = {},
                group, agent;

            for (key in agentGroupList)
            {
                if (agentGroupList.hasOwnProperty(key)) {
                    group = agentGroupList[key];
                    groups[group.id] = {
                        checked: searchGroups.indexOf(group.id) > -1,
                        group_id: null
                    };

                    for (key in group.agents)
                    {
                        if (group.agents.hasOwnProperty(key)) {
                            agent = group.agents[key];
                            agents[agent.id] = {
                                checked: !!searchAgents[agent.id],
                                group_id: group.id
                            };

                            errors[agent.id] = {
                                min : 0,
                                max : 0
                            };
                        }
                    }
                }
            }

            let releases = {},
                release;

            searchReleases.forEach(function(val, key) {
                searchReleases[key] = parseInt(val);
            });

            for (key in releaseList)
            {
                if (releaseList.hasOwnProperty(key)) {
                    release = releaseList[key];
                    releases[release.id] = searchReleases.indexOf(release.id) > -1;
                }
            }

            let categories = {},
                parent, child;

            searchCategories.forEach(function(val, key) {
                searchCategories[key] = parseInt(val);
            });

            for (key in categoryGroupList)
            {
                if (categoryGroupList.hasOwnProperty(key)) {
                    parent = categoryGroupList[key];

                    categories[parent.id] = {
                        checked: searchCategories.indexOf(parent.id) > -1,
                        parent_id: null
                    };

                    for (key in parent.children)
                    {
                        if (parent.children.hasOwnProperty(key)) {
                            child = parent.children[key];

                            categories[child.id] = {
                                checked: searchCategories.indexOf(child.id) > -1,
                                parent_id: parent.id
                            };
                        }
                    }
                }
            }

            return {
                buttonText: 'Найти',

                categoryGroupList: categoryGroupList,
                categories: categories,
                releaseList: releaseList,
                releases: releases,
                agentGroupList: agentGroupList,
                groups: groups,
                agents: agents,
                searchAgentsPrepared : searchAgentsPrepared,
                searchAgentsText: null,
                searchResult: {},
                errors: errors,

                categoriesString: '',
                releasesString: '',
                agentsString: '',

                queried: 0
            }
        },
        mounted() {
            this.updateSearchAgentText();
        },
        props: [
            'product_search_agents_endpoint',
            'product_search_agents_click_endpoint',
            'category_group_list',
            'search_categories',
            'release_list',
            'search_releases',
            'agent_group_list',
            'search_agents',
            'search_groups',
            'search_agents_prepared',
            'search_query'
        ],
        methods: {
            collectData()
            {
                if (!this.queried) {
                    let key, group_id, agent_id, values, groupAgents,
                        categories = [],
                        releases = [],
                        agents = [];

                    for (key in this.categories)
                        if (this.categories.hasOwnProperty(key))
                            categories.push(key);

                    for (key in this.releases)
                        if (this.releases.hasOwnProperty(key))
                            releases.push(key);

                    for (group_id in this.searchAgentsPrepared) {
                        if (this.searchAgentsPrepared.hasOwnProperty(group_id)) {
                            groupAgents = this.searchAgentsPrepared[group_id];
                            for (agent_id in groupAgents) {
                                if (groupAgents.hasOwnProperty(agent_id)) {
                                    values = groupAgents[agent_id];

                                    agents.push([agent_id, values[0], values[1]].join(','));
                                }
                            }
                        }
                    }

                    this.agentsString = agents.join('|');
                    this.releasesString = releases.join(',');
                    this.categoriesString = categories.join(',');

                    this.buttonText = '<i class="fas fa-circle-notch fa-spin"></i>';

                    axios
                        .post(this.product_search_agents_click_endpoint, {
                            agents: this.agentsString,
                            releases: this.releasesString,
                            categories: this.categoriesString,
                        })
                        .then(response => {
                            this.$refs.form.submit();
                        })
                        .catch(error => console.log(error.response));
                }
            },
            resetAgentValues(agent_id, group_id)
            {
                let agent = this.agentGroupList[group_id]['agents'][agent_id];

                this.searchAgentsPrepared[group_id][agent_id] = [agent.min, agent.max];
            },
            validateAgentValue($event, agent_id, type)
            {
                let value = $event.target.value,
                    error = 0;

                if (value && !/^([0-9]+[\.]{0,1}[0-9]*)$/.test(value))
                    error++;

                if (!error)
                    if ( (value < 0) || (value > 100) )
                        error++;

                error
                    ? this.errors[agent_id][type] = error
                    : this.errors[agent_id][type] = 0;
            },
            searchAgent($event) {
                let term = $event.target.value,
                    searchResult = {};

                if (term) {

                    let key, group_id, group, agent, groupTitle, agentTitle,

                        agentGroupList = this.agentGroupList;

                    for (group_id in agentGroupList)
                    {
                        if (agentGroupList.hasOwnProperty(group_id)) {
                            group = agentGroupList[group_id];

                            groupTitle = group.title_short.toLowerCase();
                            if (groupTitle.indexOf(term) > -1)
                                searchResult[group_id] = [];

                            for (key in group.agents)
                            {
                                if (group.agents.hasOwnProperty(key)) {
                                    agent = group.agents[key];

                                    agentTitle = agent.title.toLowerCase();
                                    if (agentTitle.indexOf(term) > -1) {
                                        if (!searchResult[group_id])
                                            searchResult[group_id] = [];

                                        searchResult[group_id].push(agent.id);
                                    }
                                }
                            }
                        }
                    }
                }

                this.searchResult = searchResult;
            },
            toggleAgents(status)
            {
                let groups          = this.groups,
                    id;

                for (id in groups)
                    if (groups.hasOwnProperty(id))
                        groups[id].checked = status;

                this.updateSearchAgentPrepared();
                this.updateSearchAgentText();
            },
            updateAgents($event, type)
            {
                let el                      = $event.target,
                    agents                  = this.agents,
                    groups                  = this.groups,
                    agentGroupList          = this.agentGroupList,
                    group_id;

                if (type === 'agent') {
                    let agent_id = parseInt(el.value);

                    group_id = agents[agent_id].group_id;

                    agents[agent_id].checked = el.checked;

                    if (!el.checked) {

                        let groupAgents = agentGroupList[group_id].agents,
                            key, agent,
                            checkGroup = false;

                        for (key in groupAgents) {
                            if (groupAgents.hasOwnProperty(key)) {
                                agent = groupAgents[key];
                                if (agents[agent.id].checked) {
                                    checkGroup = true;
                                    break;
                                }
                            }
                        }
                        groups[group_id].checked = checkGroup;
                    }
                }

                if (type === 'group') {
                    group_id = parseInt(el.value);

                    let groupAgents = agentGroupList[group_id].agents,
                        key, agent;

                    groups[group_id].checked = el.checked;

                    for (key in groupAgents)
                    {
                        if (groupAgents.hasOwnProperty(key)) {
                            agent = groupAgents[key];
                            agents[agent.id].checked = el.checked;
                        }
                    }
                }

                this.updateSearchAgentPrepared();
                this.updateSearchAgentText();
            },
            updateSearchAgentText()
            {
                let searchAgentText = [],
                    searchAgentsPrepared = this.searchAgentsPrepared,
                    group_id, group, size, title, count;

                let getSize = function (object) {
                    let size = 0, key;
                    for (key in object)
                        if (object.hasOwnProperty(key))
                            size++;

                    return size;
                };

                for (group_id in searchAgentsPrepared)
                {
                    if (searchAgentsPrepared.hasOwnProperty(group_id)) {
                        group = this.agentGroupList[group_id];
                        title = group.title_short;
                        count = group.agents_count;
                        size = getSize(searchAgentsPrepared[group_id]);

                        (size === count)
                            ? searchAgentText.push(title)
                            : searchAgentText.push(title + ' из отмеченных ' + '(' + size + ')');
                    }
                }

                this.searchAgentsText = searchAgentText.length ? searchAgentText : null;
            },
            updateSearchAgentPrepared()
            {
                let searchGroups            = this.groups,
                    searchAgents            = this.agents,
                    agentGroupList          = this.agentGroupList,
                    searchAgentsPrepared    = {},
                    agents                  = {},
                    group_id, agent_id, group, agent;

                for (group_id in searchGroups)
                {
                    if (searchGroups.hasOwnProperty(group_id)) {
                        if (searchGroups[group_id].checked) {

                            group = agentGroupList[group_id];
                            agents = {};

                            for (agent_id in group.agents)
                                if (group.agents.hasOwnProperty(agent_id))
                                    if (searchAgents[agent_id].checked) {
                                        agent = group.agents[agent_id];
                                        agents[agent_id] = [agent.min, agent.max];
                                    }

                            searchAgentsPrepared[group_id] = agents;
                        }
                    }
                }

                this.searchAgentsPrepared = searchAgentsPrepared;

            },
            toggleReleases(status)
            {
                let releases = this.releases,
                    id;

                for (id in releases)
                    if (releases.hasOwnProperty(id))
                        releases[id] = status;
            },
            updateReleases($event)
            {
                let el          = $event.target,
                    releases    = this.releases,
                    release_id  = parseInt(el.value);

                releases[release_id] = el.checked;
            },
            toggleCategories(status)
            {
                let categories = this.categories,
                    id;

                for (id in categories)
                    if (categories.hasOwnProperty(id))
                        categories[id].checked = status;
            },
            updateCategories($event)
            {
                let el          = $event.target,
                    categories  = this.categories,
                    category_id = parseInt(el.value),
                    parent_id   = categories[category_id].parent_id,
                    id;

                categories[category_id].checked = el.checked;

                if (parent_id) {
                    let checkParent = false;

                    if (el.checked) {
                        checkParent = true;

                        for (id in categories)
                            if (categories.hasOwnProperty(id))
                                if ((categories[id].parent_id === parent_id) && !categories[id].checked)
                                    checkParent = false; // можно сделать break
                    }
                    categories[parent_id].checked = checkParent;
                } else {
                    parent_id = category_id;

                    for (id in categories)
                        if (categories.hasOwnProperty(id))
                            if (categories[id].parent_id === parent_id)
                                categories[id].checked = el.checked;
                }
            }
        }
    }
</script>