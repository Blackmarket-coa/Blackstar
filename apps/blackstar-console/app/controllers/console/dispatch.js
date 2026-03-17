import Controller from '@ember/controller';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

const STATUSES = ['all', 'open', 'bid', 'assigned', 'in_transit', 'delivered'];

export default class ConsoleDispatchController extends Controller {
    statuses = STATUSES;

    @tracked status = 'all';

    orders = [
        { id: 'ord_1001', status: 'open', pickup: 'Queens', dropoff: 'Brooklyn', bids: 3, driver: null },
        { id: 'ord_1002', status: 'assigned', pickup: 'Midtown', dropoff: 'Harlem', bids: 2, driver: 'drv_019' },
        { id: 'ord_1003', status: 'in_transit', pickup: 'SoHo', dropoff: 'Chelsea', bids: 1, driver: 'drv_031' },
    ];

    get filteredOrders() {
        if (this.status === 'all') {
            return this.orders;
        }

        return this.orders.filter((order) => order.status === this.status);
    }

    @action setStatusFilter(event) {
        this.status = event.target.value;
    }
}
