import { module, test } from 'qunit';
import Route from '@ember/routing/route';
import { setupTest } from '@fleetbase/console/tests/helpers';

module('Unit | Route | console/admin/config/database', function (hooks) {
    setupTest(hooks);

    test('is a passive route shell for admin database config screen', function (assert) {
        const route = this.owner.lookup('route:console/admin/config/database');
        const ownMethods = Object.getOwnPropertyNames(Object.getPrototypeOf(route)).filter((name) => name !== 'constructor');

        assert.true(route instanceof Route, 'inherits from Ember Route to participate in routing lifecycle');
        assert.deepEqual(ownMethods, [], 'route intentionally defines no custom hooks yet');
    });
});
