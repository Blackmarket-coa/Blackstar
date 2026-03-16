import { module, test } from 'qunit';
import Service from '@ember/service';
import { setupTest } from '@fleetbase/console/tests/helpers';

class AppCacheStubService extends Service {
    cache = new Map();

    set(key, value) {
        this.cache.set(key, value);
    }

    get(key) {
        return this.cache.get(key);
    }
}

module('Unit | Service | onboarding-context', function (hooks) {
    setupTest(hooks);

    hooks.beforeEach(function () {
        this.owner.register('service:app-cache', AppCacheStubService);
    });

    test('merge filters sensitive fields and persists non-sensitive data', function (assert) {
        const service = this.owner.lookup('service:onboarding-context');

        service.merge(
            {
                companyName: 'Blackstar Logistics',
                password: 'should-not-persist',
                password_confirmation: 'should-not-persist',
            },
            { persist: true }
        );

        assert.strictEqual(service.data.companyName, 'Blackstar Logistics', 'non-sensitive field is merged into service state');
        assert.false('password' in service.data, 'password is excluded from in-memory data');
        assert.strictEqual(service.getFromCache('companyName'), 'Blackstar Logistics', 'non-sensitive field is persisted to appCache');
    });

    test('reset clears persisted keys and runtime status flags', function (assert) {
        const service = this.owner.lookup('service:onboarding-context');

        service.persist('companyName', 'Blackstar Logistics');
        service.quotaExceeded = true;
        service.usingMemoryFallback = true;

        service.reset();

        assert.deepEqual(service.data, {}, 'runtime onboarding context is cleared');
        assert.strictEqual(service.getFromCache('companyName'), undefined, 'persisted context value is removed');
        assert.deepEqual(service.getStorageStatus(), { quotaExceeded: false, usingMemoryFallback: false, memoryItemCount: 0 }, 'storage status flags are restored to healthy defaults');
    });
});
