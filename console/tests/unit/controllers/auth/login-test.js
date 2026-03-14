import { module, test } from 'qunit';
import Service from '@ember/service';
import { setupTest } from '@fleetbase/console/tests/helpers';

class RouterStubService extends Service {
    transitionCalls = [];

    transitionTo(...args) {
        this.transitionCalls.push(args);
        return Promise.resolve();
    }
}

class UrlSearchParamsStubService extends Service {
    value = null;

    get(param) {
        return param === 'shift' ? this.value : null;
    }
}

class SessionStubService extends Service {
    redirect = null;

    setRedirect(route) {
        this.redirect = route;
    }
}

module('Unit | Controller | auth/login', function (hooks) {
    setupTest(hooks);

    hooks.beforeEach(function () {
        this.owner.register('service:router', RouterStubService);
        this.owner.register('service:url-search-params', UrlSearchParamsStubService);
        this.owner.register('service:session', SessionStubService);
    });

    test('setRedirect stores redirect route when shift query param is present', function (assert) {
        const controller = this.owner.lookup('controller:auth/login');
        const session = this.owner.lookup('service:session');
        const urlSearchParams = this.owner.lookup('service:url-search-params');

        urlSearchParams.value = '/console/admin/config/database';
        controller.setRedirect();

        assert.strictEqual(session.redirect, 'console.admin.config.database', 'shift is converted into route name and persisted for post-login redirect');
    });

    test('forgotPassword transitions and forwards the typed email', async function (assert) {
        const controller = this.owner.lookup('controller:auth/login');
        const router = this.owner.lookup('service:router');
        const forgotPasswordController = this.owner.lookup('controller:auth/forgot-password');

        controller.email = 'ops@example.com';
        await controller.forgotPassword();

        assert.deepEqual(router.transitionCalls[0], ['auth.forgot-password'], 'navigates to forgot-password route');
        assert.strictEqual(forgotPasswordController.email, 'ops@example.com', 'copies current email into forgot-password controller state');
    });
});
