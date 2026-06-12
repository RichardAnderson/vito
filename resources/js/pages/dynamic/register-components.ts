import { registerComponent } from './component-registry';
import PageComponent from './components/page';
import CardComponent from './components/card';
import CardRowComponent from './components/card-row';
import ButtonComponent from './components/button';
import AlertComponent from './components/alert';
import TableComponent from './components/table';
import LogViewComponent from './components/log-view';
import ControlComponent from './components/control';

/**
 * Core component registrations. Imported for its side effect by the dynamic page
 * entry. Plugin/v2 bundles register additional types into the same mutable Map.
 */
registerComponent('page', PageComponent);
registerComponent('card', CardComponent);
registerComponent('card-row', CardRowComponent);
registerComponent('button', ButtonComponent);
registerComponent('alert', AlertComponent);
registerComponent('table', TableComponent);
registerComponent('log-view', LogViewComponent);
registerComponent('control', ControlComponent);
