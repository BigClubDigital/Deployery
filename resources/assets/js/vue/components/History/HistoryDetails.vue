<style lang='css' scoped>
.history-file-panel {
    max-height: 300px;
    overflow: auto;
}
</style>

<template>
<form-modal @close='close'>
    <template slot="header">
        <h4 class="modal-title" v-html='historyHeader'></h4>
    </template>

    <template slot="body">
        <loading-indicator v-if='loading'></loading-indicator>
        <div class='row' v-else>
            <div class="col-12 w-100 my-2">
                <form-card>
                <div slot="header"><b>Info</b></div>
                <div class="history-file-panel">
                    <ul class="list-unstyled">
                        <li><b>{{ statusMessage }}</b></li>
                        <li>Deployed by: <b>{{ history.user_name }}</b></li>
                        <li>Deployed on: <b>{{ history.created_at }}</b></li>
                    </ul>
                </div>
                </form-card>
            </div>

            <div v-if='errors.length' class="col-12 my-2">
                <form-card>
                <div slot="header"><b>Errors</b></div>
                <div class="history-file-panel">
                    <ul class="list-unstyled">
                        <li v-for='error in errors'>{{ error }}</li>
                    </ul>
                </div>
                </form-card>
            </div>

            <template v-for='(value, key, idx) in details'>
                <div class="col-6 col-sm-12 col-md-6 my-2">
                    <form-card>
                    <div slot="header"><b>{{ key }}</b></div>
                    <div class="history-file-panel">
                        <ul v-if='value.length' class="list-unstyled">
                            <li v-for='file in value'>{{ file }}</li>
                        </ul>
                        <h5 v-else>N/A</h5>
                    </div>
                    </form-card>
                </div>
                <div v-if='idx % 2' class="w-100"></div>
            </template>
        </div>
    </template>
</form-modal>
</template>

<script>
export default {
    props: {
        endpoint: {
            type: String,
            required: true
        },
    },

    data() {
        return {
            history: {},
            loading: false,
        }
    },

    mounted () {
        this.load();
    },

    computed: {

        details () {
            return {
                "Successful Uploads": this.successfulUploads,
                "Failed Uploads": this.failedUploads,
                "Successful Removals": this.successfulRemovals,
                "Failed Removals": this.failedUploads
            }
        },

        historyHeader () {
            if(!this.history || this.loading) {
                return "Getting details...";
            }
            return "Deployed from <b>"+ this.history.from_commit +"</b> to <b>"+ this.history.to_commit;
        },

        statusMessage () {
            var status = _.get(this.history, 'success', true);
            return  "Deployment " + (status ? "was successful" : "failed");
        },

        errors () {
            return _.get(this.history, 'details.errors', []);
        },

        successfulUploads () {
            return _.get(this.history, 'details.changes.uploaded.success',[]);
        },

        failedUploads () {
            return _.get(this.history, 'details.changes.uploaded.failed', []);
        },

        successfulRemovals () {
            return _.get(this.history, 'details.changes.removed.success', []);
        },

        failedRemovals () {
            return _.get(this.history, 'details.changes.removed.failed', []);
        }
    },

    methods: {
        load () {
            this.loading = true;
            var endpoint = `${this.endpoint}/history/${this.$route.params.id}`

            this.$http.get(endpoint).then((response)=>{
                this.history = response.data.data;
            }, ({response})=>{
                console.error(response)
            }).then(()=>{this.loading=false});
        },

        close() {
            this.$router.go(-1)
        }
    }
}
</script>