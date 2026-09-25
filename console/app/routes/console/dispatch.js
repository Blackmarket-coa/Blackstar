import Route from '@ember/routing/route';

export default class ConsoleDispatchRoute extends Route {
    setupController(controller) {
        super.setupController(...arguments);
        controller.loadListings();
    }
}
