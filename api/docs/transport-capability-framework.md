# Transport-Agnostic Capability Framework

## TransportClass

`TransportClass` defines a normalized capability profile for a node.

Fields:

- `category`
- `subtype`
- `weight_limit`
- `range_limit`
- `hazard_capability`
- `regulatory_class`
- `insurance_required_flag`

## Node capability assignment

Nodes are linked to one or more transport classes via `node_transport_classes`.

## Shipment listing constraints

Shipment board listings can express transport requirements using constraint fields:

- `required_category`
- `required_subtype`
- `required_weight_limit`
- `required_range_limit`
- `requires_hazard_capability`
- `required_regulatory_class`
- `insurance_required_flag`

## Matching behavior

Matching is constraints-only and transport-agnostic:

- status must be `open`
- node jurisdiction must match listing jurisdiction when specified
- at least one node transport class must satisfy all listing constraints

Route optimization and internal vehicle assignment are intentionally out of scope.
