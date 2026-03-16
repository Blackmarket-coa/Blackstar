import { module, test } from 'qunit';
import Service from '@ember/service';
import { setupTest } from '@fleetbase/console/tests/helpers';

class SessionStubService extends Service {
    prohibitAuthenticationCalls = [];

    prohibitAuthentication(routeName) {
        this.prohibitAuthenticationCalls.push(routeName);
    }
}

class UniverseStubService extends Service {
    calls = [];

    virtualRouteRedirect(transition, source, destination, options) {
        this.calls.push({ transition, source, destination, options });
        return 'virtual-redirect-result';
    }
}

module('Unit | Route | auth/login', function (hooks) {
    setupTest(hooks);

    hooks.beforeEach(function () {
        this.owner.register('service:session', SessionStubService);
        this.owner.register('service:universe', UniverseStubService);
    });

    test('beforeModel blocks authenticated access and applies virtual redirect', function (assert) {
        const route = this.owner.lookup('route:auth/login');
        const session = this.owner.lookup('service:session');
        const universe = this.owner.lookup('service:universe');
        const transition = { to: { name: 'auth.login' } };

        const result = route.beforeModel(transition);

        assert.deepEqual(session.prohibitAuthenticationCalls, ['console'], 'prevents authenticated users from re-entering login route');
        assert.deepEqual(
            universe.calls[0],
            {
                transition,
                source: 'auth:login',
                destination: 'virtual',
                options: { restoreQueryParams: true },
            },
            'calls universe virtualRouteRedirect with expected source and options'
        );
        assert.strictEqual(result, 'virtual-redirect-result', 'returns universe redirect result for routing pipeline');
    });
});
