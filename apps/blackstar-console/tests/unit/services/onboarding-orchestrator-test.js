import { module, test } from 'qunit';
import Service from '@ember/service';
import { setupTest } from '@fleetbase/console/tests/helpers';

class OnboardingRegistryStubService extends Service {
    defaultFlow = 'default';
    flow = {
        id: 'default',
        entry: 'start',
        steps: [
            { id: 'start', next: 'finish' },
            { id: 'finish', next: null },
        ],
    };

    getFlow(flowId) {
        return flowId === 'default' ? this.flow : null;
    }
}

class OnboardingContextStubService extends Service {}

module('Unit | Service | onboarding-orchestrator', function (hooks) {
    setupTest(hooks);

    hooks.beforeEach(function () {
        this.owner.register('service:onboarding-registry', OnboardingRegistryStubService);
        this.owner.register('service:onboarding-context', OnboardingContextStubService);
    });

    test('start initializes flow state and enters configured entry step', async function (assert) {
        const service = this.owner.lookup('service:onboarding-orchestrator');

        await service.start();

        assert.strictEqual(service.flow.id, 'default', 'default flow is selected from registry');
        assert.strictEqual(service.current.id, 'start', 'entry step becomes current after start');
        assert.deepEqual(service.history, [], 'history is reset when flow starts');
    });

    test('next advances to subsequent step and tracks history', async function (assert) {
        const service = this.owner.lookup('service:onboarding-orchestrator');

        await service.start();
        await service.next();

        assert.strictEqual(service.current.id, 'finish', 'next navigates to configured next step');
        assert.deepEqual(
            service.history.map((step) => step.id),
            ['start'],
            'leaving step is persisted into history'
        );
    });
});
