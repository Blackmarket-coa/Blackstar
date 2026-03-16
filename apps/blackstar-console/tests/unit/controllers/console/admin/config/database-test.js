import { module, test } from 'qunit';
import Controller from '@ember/controller';
import { setupTest } from '@fleetbase/console/tests/helpers';

module('Unit | Controller | console/admin/config/database', function (hooks) {
    setupTest(hooks);

    test('remains a thin state-only controller without custom actions yet', function (assert) {
        const controller = this.owner.lookup('controller:console/admin/config/database');
        const ownMethods = Object.getOwnPropertyNames(Object.getPrototypeOf(controller)).filter((name) => name !== 'constructor');

        assert.true(controller instanceof Controller, 'inherits from Ember Controller for template state wiring');
        assert.deepEqual(ownMethods, [], 'no custom controller behavior is defined unexpectedly');
    });
});
