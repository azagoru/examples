<!-- Search component -->
<!-- https://orion-med.ru -->

<template>
    <div class="search-input">

        <form method="GET" :action="productSearchEndpoint" >
            <div class="position-relative">
                <div class="d-flex input-group">
                    <input
                            @keyup="searchTimeout"
                            v-model="text"

                            autocomplete="off"
                            id="search-input"
                            class="form-control"
                            type="text"
                            name="term"
                            :title="inputText"
                            :placeholder="inputText" />

                    <div class="input-group-append">
                        <button
                                id="search-button"
                                class="btn btn-primary"
                                v-html="buttonText"
                        ></button>
                    </div>
                </div>

                <div v-if="items && !isHidden" class="search-result shadow-xl" v-click-outside="hide">
                    <ul v-if="length" class="list-unstyled mb-0">
                        <li v-for="item in items" :class="item.type">
                            <a :href="item.href" :title="item.title">
                                {{ item.title }}<br/>
                                <span class="price" v-html="item.price"></span>
                            </a>
                        </li>
                    </ul>
                    <div v-else class="p-2">
                        По запросу &laquo;{{ this.text }}&raquo; товаров не найдено!
                    </div>
                </div>
            </div>
        </form>

    </div>
</template>

<script>
    export default
    {
        data () {
            return {
                inputText: 'Искать дезсредство',
                buttonText: 'Найти',

                text: this.term,
                items: null,
                length: null,
                isHidden: true,
                timer: null,
                productSearchDataEndpoint: this.product_search_data_endpoint,
                productSearchEndpoint: this.product_search_endpoint,
                charWidth: charWidth
            }
        },

        props: [
            'term',
            'product_search_endpoint',
            'product_search_data_endpoint'
        ],

        methods: {
            searchTimeout()
            {
                clearTimeout(this.timer);

                let $this = this;

                this.timer = setTimeout(function()
                {
                    $this.search();
                }, 450);
            },
            search()
            {
                if (this.text) {

                    this.buttonText = '<i class="fas fa-circle-notch fa-spin"></i>';
                    axios
                        .post(this.productSearchDataEndpoint, {term: this.text})
                        .then(response => {

                            if ([9,10].indexOf(this.charWidth) === -1) {
                                response.data.forEach(function (item, key) {
                                    response.data[key].price = item.price.replace('&#8381;', 'руб.');
                                });
                            }

                            this.items = response.data;
                            this.length = response.data.length;
                            this.empty = response.data.length;
                            this.isHidden = false;
                            this.buttonText = 'Найти';
                        })
                        .catch(error => console.log(error.response));
                } else {
                    this.hide();
                }
            },
            hide: function()
            {
                this.isHidden = true;
            }
        },
        directives: {
            'click-outside': {
                bind: function (el, binding, vnode)
                {
                    let parent = document.getElementById('search-input');

                    el.clickOutsideEvent = function (event)
                    {
                        if (
                                !(
                                        el == event.target
                                        || el.contains(event.target)
                                        || parent == event.target
                                        || parent.contains(event.target)
                                )
                        ) {
                            vnode.context[binding.expression](event);
                        }
                    };
                    document.body.addEventListener('click', el.clickOutsideEvent)
                },
                unbind: function (el)
                {
                    document.body.removeEventListener('click', el.clickOutsideEvent)
                }
            }
        }
    }
</script>