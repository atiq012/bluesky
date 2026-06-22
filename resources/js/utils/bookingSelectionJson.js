export function buildSelectionJson({ flight, selectedBrand, form, contentSource }) {
    if (!flight) return null

    const outbound = flight.outbound ?? {}
    const inbound = flight.inbound ?? null

    return {
        content_source: contentSource ?? null,
        refund_type: outbound.refund_type ?? null,
        form: form ? {
            Way: form.Way,
            from: form.from,
            to: form.to,
            dep_date: form.dep_date,
            arrival_date: form.arrival_date,
            ADT: form.ADT,
            CNN: form.CNN,
            INF: form.INF,
            cabin_class: form.cabin_class,
        } : null,
        outbound: {
            offering_id: outbound._offering_id,
            product_ref: selectedBrand?._productRef ?? outbound._selected_productRef,
            brand_ref: selectedBrand?._brandRef,
            brand_label: selectedBrand?.label,
            carrier_code: outbound.first_carrier_code ?? outbound.carrier_code ?? null,
            airline: outbound.airline_name ?? outbound.airline,
            departure: outbound.departure,
            arrival: outbound.arrival,
            duration: outbound.duration,
        },
        inbound: inbound ? {
            offering_id: inbound._offering_id,
            product_ref: inbound._selected_productRef,
            carrier_code: inbound.first_carrier_code ?? inbound.carrier_code ?? null,
            airline: inbound.airline_name ?? inbound.airline,
            departure: inbound.departure,
            arrival: inbound.arrival,
        } : null,
        brand: selectedBrand ? {
            label: selectedBrand.label,
            price: selectedBrand.price,
            class_of_service: selectedBrand.class_of_service,
            fare_basis_code: selectedBrand.fare_basis_code,
        } : null,
    }
}
