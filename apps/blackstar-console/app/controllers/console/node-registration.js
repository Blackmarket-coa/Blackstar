import Controller from '@ember/controller';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

export default class ConsoleNodeRegistrationController extends Controller {
    @tracked nodeName = '';
    @tracked vehicleTypes = 'bike,van';
    @tracked availabilityWindows = 'mon-fri:08:00-18:00';
    @tracked polygon = '[[40.7128,-74.0060],[40.7306,-73.9866],[40.7210,-73.9980]]';
    @tracked submission = null;

    @action submitNodeRegistration(event) {
        event.preventDefault();

        this.submission = {
            node_name: this.nodeName,
            service_area_polygon: this.polygon,
            vehicle_types: this.vehicleTypes
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean),
            availability_windows: this.availabilityWindows,
        };
    }
}
