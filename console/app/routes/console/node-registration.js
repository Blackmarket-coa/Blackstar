import Route from '@ember/routing/route';

export default class ConsoleNodeRegistrationRoute extends Route {
    setupController(controller) {
        super.setupController(...arguments);
        controller.refreshOperatorToken();
    }
}
