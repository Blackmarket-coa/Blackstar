import { module, test } from 'qunit';
import Service from '@ember/service';
import { setupTest } from '@fleetbase/console/tests/helpers';

class OnboardingOrchestratorStubService extends Service {
    calls = [];

    start(flowId, options) {
        this.calls.push({ flowId, options });
    }
}

class StoreStubService extends Service {
    lastFind = null;

    findRecord(type, id) {
        this.lastFind = { type, id };
        return { type, id };
    }
}

module('Unit | Route | onboard/index', function (hooks) {
    setupTest(hooks);

    hooks.beforeEach(function () {
        this.owner.register('service:onboarding-orchestrator', OnboardingOrchestratorStubService);
        this.owner.register('service:store', StoreStubService);
    });

    test('beforeModel resumes onboarding state from persisted session', function (assert) {
        const route = this.owner.lookup('route:onboard/index');
        const orchestrator = this.owner.lookup('service:onboarding-orchestrator');

        route.beforeModel();

        assert.deepEqual(orchestrator.calls[0], { flowId: null, options: { resume: true } }, 'route resumes default onboarding flow with resume flag');
    });

    test('model loads the singleton brand record for onboarding', function (assert) {
        const route = this.owner.lookup('route:onboard/index');
        const store = this.owner.lookup('service:store');

        const model = route.model();

        assert.deepEqual(store.lastFind, { type: 'brand', id: 1 }, 'loads brand record with expected id');
        assert.deepEqual(model, { type: 'brand', id: 1 }, 'returns the loaded brand model from store');
    });
});
