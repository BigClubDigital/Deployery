
/**
 * First we will load all of this project's JavaScript dependencies which
 * include Vue and Vue Resource. This gives a great starting point for
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

/**
 * Register components
 */
import { VueForms } from 'vue-forms'
Vue.use(VueForms);

import { ApiPlugin } from './vue/services'
Vue.use(ApiPlugin)

import ElementsPlugin from './vue/elements'
Vue.use(ElementsPlugin)

import NavPlugin from './vue/components/Nav'
Vue.use(NavPlugin)

import DeploymentPlugin from './vue/components/Deployments'
Vue.use(DeploymentPlugin)

import { Filters } from './vue/util'
Vue.use(Filters)
/**
 * Register Mixins
 */
import { LocalTime } from './vue/mixins/LocalTime.js';
Vue.mixin(LocalTime);

import { Alerter } from './alerter';
Vue.prototype.$alerter = Alerter;

import { routes as ProjectRoutes, ProjectsList, ProjectCreateModal } from './vue/components/Projects'
import { MyAccount } from './vue/components/Users'

/**
 * Setup the router
 */
const routes = [
    {
        path: '/my-account',
        name: 'my.account',
        component: MyAccount,
    },
    ProjectRoutes
]

import VueRouter from 'vue-router';
Vue.use(VueRouter);

const router = new VueRouter({
    mode: 'history',
    routes: routes,
});

window.bus = new Vue({});

import Vuex from 'vuex';
Vue.use(Vuex)

import { store as aStore } from './vue/store'
const store = new Vuex.Store(aStore)

const { user } = window.Deployery
if (!_.isEmpty(user)) {
    store.commit('user', {user,} )
}

/**
 * Setup the App
 */
const app = new Vue({
    router,
    store,
    el: '#app',

    mounted() {
        if(!this.$store.getters.hasUser) return;
        this.$store.dispatch('PROJECTS_LOAD')
    }
});
