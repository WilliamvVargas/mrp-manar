<div class="modal fade" id="modalLeadInfo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title"><i class="bi bi-info-circle me-2"></i>¿Cómo se calcula el Lead Time?</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">

                <p class="small mb-3">
                    El <b>Lead Time</b> es el tiempo real (en <b>semanas</b>) entre que se crea la Orden de
                    Compra y la mercadería llega a bodega, calculado con el <b>historial de recepciones</b>.
                    Para cada proveedor se combinan dos fuentes:
                </p>
                <ul class="small mb-3">
                    <li><b>Su propia historia</b> — cuánto suele tardar ese proveedor (pesa más mientras más recepciones tenga).</li>
                    <li><b>Su país en la temporada</b> — el promedio del país en el trimestre actual, que aporta robustez y estacionalidad (ej. la cordillera en invierno).</li>
                </ul>
                <p class="small mb-3">
                    Si el proveedor <b>no tiene historia propia</b>, el valor queda 100% según su país.
                    La columna <b>“Base del cálculo”</b> indica de dónde salió cada número:
                </p>

                <table class="table table-sm align-middle">
                    <tbody>
                        <tr>
                            <td class="text-center" style="width:150px;"><span class="badge bg-success">Historial propio</span></td>
                            <td class="small">Manda la <b>propia historia</b> del proveedor (tiene suficientes recepciones para confiar en su comportamiento).</td>
                        </tr>
                        <tr>
                            <td class="text-center"><span class="badge bg-info text-dark">Proveedor + país</span></td>
                            <td class="small"><b>Mezcla</b> de su propia historia con el promedio de su país en la temporada.</td>
                        </tr>
                        <tr>
                            <td class="text-center"><span class="badge bg-primary">País y temporada</span></td>
                            <td class="small">Sin historia propia: usa el promedio de su <b>país en el trimestre actual</b> (con estacionalidad).</td>
                        </tr>
                        <tr>
                            <td class="text-center"><span class="badge bg-warning text-dark">País (anual)</span></td>
                            <td class="small">El trimestre tenía <b>pocos datos</b>: usa el promedio del país de <b>todo el año</b> (sin separar temporada).</td>
                        </tr>
                        <tr>
                            <td class="text-center"><span class="badge bg-secondary">Estimado</span></td>
                            <td class="small">No hay historia del país: es un <b>estimado general</b> del promedio de importaciones.</td>
                        </tr>
                    </tbody>
                </table>

            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
