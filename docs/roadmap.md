# Deployery Roadmap

## v0.2

### Backend
- [x] User based SSH Key generation used in connecting to Git hosts
- [x] Slack
- [x] Configure site settings in backend
- [x] Subclass \Remote class to support SFTP zero byte issue
- [x] Validate the source of the webhook request
- [x] Migrate API Auth to JWT
- [ ] Add unit tests, setup [TravisCI](https://travis-ci.org)
- [x] Setup [Scrutinizer CI](https://scrutinizer-ci.com/pricing)
- [x] Migrate to Users -> Teams

### Frontend
- [x] Add Settings page
- [x] Add confirmation handling to related model delete requests (trash can)
- [x] Fix secondary nav bar hi-light height
- [x] Add global deploy button somewhere
- [x] Add more info to the dashborad
- [x] Change deployment panel, Make `to` and `from` disabled by default
- [x] Update deployment panel to be filterable.
- [x] Change local of deploying message on main project page.
- [ ] Display Server IP address and message about whitelisting it on deployment targets
- [x] Add display of SSH pubkey in server tab.

## v0.3

### Backend
- [ ] Cancel deployment operation
- [ ] Add wider array of webhook sources (see config/webhook.php)
- [ ] Auto spawn project specific queues
- [ ] Support cloud file storage
- [x] Do a pre-run test on server to check writability of every directory
- [x] Improve error handling
- [ ] Explore alternative handling of run scripts, currently they don't support shebang.
- [ ] Support HTTP login for remote git repositories
- [ ] localize strings