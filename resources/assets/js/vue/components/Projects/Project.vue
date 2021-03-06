<template>
<div>
    <project-links v-bind="{status, hasServers}"></project-links>

    <!-- Main Body -->
    <div class="col mt-4">
        <div class='col my-3'>
            <project-cloning-card :repo='project.repo' :status='status' @reclone='cloneRepo'></project-cloning-card>
        </div>

        <router-view v-bind='{loading, info}' :project.sync='project' />

        <deployments-info-panel></deployments-info-panel>

        <transition name='bottomup'>
            <div v-if='loading' class="bottom-up loading">
                Getting Project Data...
            </div>
        </transition>
    </div>
</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex';

import { EchoListener } from './mixins/EchoListener';

import ProjectCloningCard from './ProjectCloningCard'
import ProjectLinks from './ProjectLinks'

export default {
    name: 'project',

    components: {
        ProjectCloningCard,
        ProjectLinks
    },

    mixins: [ EchoListener ],

    data () {
        return {
            project: {},

            errors: [],
            formErrors: {},
            confirm: null,
            viewers: [],

            loading: true,
            saving: false,
            deleting: false,
            info: null,

            info: {
                deployments: {},
                repo: {},
                status: {},
            },

            status: {
                cloning: false,
                cloningError: false,
                message: "",
                errors: []
            },
        }
    },

    mounted () {
        this.load()
        this.listen()
    },

    beforeDestroy() {
        this.stopListening()
        this.resetProject();
    },

    computed : {
        ...mapState(['actionTypes']),

        endpoint () {
            const { project_id } = this.$route.params
            return '/api/projects/' + project_id
        },

        loaded () {
            return !this.loading;
        },

        hasServers () {
            return this.hasProp('servers');
        },

        hasConfig () {
            return this.hasProp('configs');
        },

    },

    watch: {
        $route (newRoute, oldRoute) {
            if (newRoute.params.project_id !== oldRoute.params.project_id) {
                this.removeEchoListener(oldRoute)
                    .addEchoListeners();

                this.load();
                bus.$emit('project-refresh-info');
            }
        },
    },

    methods : {
        isActive(type) {
            return this.$route.name === type;
        },

        load () {
            this.resetProject();
            this.loadProject();
            this.loadHistory();
            this.loadInfo();
        },

        resetProject() {
            this.$store.dispatch(this.actionTypes.PROJECT_RESET)
        },

        loadProject() {
            this.loading = true;
            this.$http.get(this.endpoint).then(
                (response)=>{
                    this.loading = false;
                    this.project = response.data.data;
                },
                ({response})=>{
                    this.loading = false;
                    this.$vfalert.error(response.data.message)
                    console.error('Error getting project', response, this.endpoint);
            });
        },

        loadHistory() {
            this.$http.get(this.endpoint + '/history').then((response)=>{
                const history = response.data.data
                this.$store.dispatch(this.$store.state.actionTypes.HISTORY_SET, {history,})
            }, ({response})=>{
                console.error("error", response);
            });
        },

        loadInfo() {
            this.$http.get(this.endpoint + '/info').then((response)=>{
                this.info = this.updateInfo(response.data);
            },({response})=>{
                console.error("error", response);
            });
        },

        listen () {
            bus.$on('delete-project-item', this.deleteDataFromProject);
            bus.$on('add-project-item', this.appendProjectData);
            bus.$on('project-info', this.updateInfo);
            bus.$on('project-refresh-info', this.loadInfo);

        },

        stopListening() {
            bus.$off('delete-project-item', this.deleteDataFromProject);
            bus.$off('add-project-item', this.appendProjectData);
            bus.$off('project-info', this.updateInfo);
            bus.$on('project-refresh-info', this.loadInfo);
        },


        updateInfo (info) {
            this.info = info;
            console.log("updating info", {info, });
            this.status.cloning = info.status.is_cloning;
            this.status.cloningError = info.status.clone_failed;
            return info;
        },

        hasProp (prop) {
            return Boolean(_.get(this.project, prop, []).length);
        },

        /**
         * Appends data to a related project array
         *
         * @param  object    object    object to be appended
         * @param  string    type     the array key / relationship
         * @param  bool      beginning  sho
         *
         */
        appendProjectData(object, type){
            if(type && this.project[type]){
                var idx = _.findIndex(this.project[type], ['id', object.id]);
                if (idx !== -1) {
                    console.log('replacing object', type);
                    this.$set(this.project[type], idx, object);
                } else {
                    console.log('adding object', type);
                    this.project[type].push(object);
                }
            }
        },

        /**
         * Remove data from a related project array
         *
         * @param  object    object    object to be appended
         * @param  string    type     the array key / relationship
         *
         */
        removeProjectData(object, type){
            this.project[type] = _.filter(this.project[type], (item)=>{
                return item.id !== object.id;
            });
        },

        /**
         * Remove an item from one of the project's related classes
         *
         * @param  object object    the object to be removed
         * @param  string type      the relationship's key in the project object
         *
         */
        deleteDataFromProject(object, type){
            if( ! confirm(`Are you sure you want to remove this item`))return;

            if(object.id && type){
                var endpoint = this.endpoint+'/'+type+'/'+object.id;
                this.$http.delete(endpoint).then(
                    (response) => {
                        this.removeProjectData(object, type);
                    },
                    ({response}) => {
                        console.error('[Error Deleting '+type+' ]', response);
                });
            }
        },

        cloneRepo({repo,}){
            var endpoint = this.endpoint+'/clone-repo'
            this.status.cloningError = false
            this.status.cloning = true
            this.project.repo = repo
            this.$http.post(endpoint, {repo,}).then(
                (response) => {
                    // this.$vfalert.toast(response.data.message)
                },
                ({response}) => {
                    this.status.false = true;
                    this.$vfalert.error(response.data.message)
            });
        },
    }
}
</script>

<style>
.bottom-up.loading {
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 60px;
    opacity: .5;
    z-index: 1000;
    border: none;
    background-color: #96a8ad;
}
</style>