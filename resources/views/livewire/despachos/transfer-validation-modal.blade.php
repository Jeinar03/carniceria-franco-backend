<div wire:ignore.self class="modal fade" id="transferValidationModal" tabindex="-1" role="dialog" style="backdrop-filter: blur(10px); z-index: 1220;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">
                    <b>{{ $componentName }}</b> | Validar transferencia
                </h5>
                <h6 class="text-center text-dark" wire:loading>POR FAVOR ESPERE</h6>
            </div>

            <div class="modal-body">
                @php
                    $transferData = $transferValidationData ?? [];
                    $hasTransferSale = !empty($transferData['id']);
                @endphp

                @if($hasTransferSale)
                    <div class="alert alert-light border">
                        <div class="row">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <div class="text-muted">Folio</div>
                                <strong>{{ $transferData['folio'] ?? 'N/A' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted">Estado transferencia</div>
                                <span class="badge badge-{{ ($transferData['transferencia_estado'] ?? 'pendiente') === 'aprobada' ? 'success' : (($transferData['transferencia_estado'] ?? 'pendiente') === 'rechazada' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($transferData['transferencia_estado'] ?? 'pendiente') }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="text-muted">Cliente</div>
                            <strong>{{ $transferData['customer_nombre'] ?? 'Cliente General' }}</strong>
                        </div>
                    </div>

                    @if(!empty($transferData['transferencia_evidencia_url']))
                        @php
                            $extension = strtolower(pathinfo($transferData['transferencia_evidencia_path'] ?? '', PATHINFO_EXTENSION));
                            $isPdf = $extension === 'pdf';
                            $evidenceUrl = $transferData['transferencia_evidencia_url'];
                        @endphp

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <strong><i class="fas fa-paperclip mr-1"></i>Evidencia de transferencia</strong>
                                <small class="text-muted d-block">{{ $isPdf ? 'Documento PDF' : 'Imagen' }}</small>
                            </div>
                            <a href="{{ $evidenceUrl }}" target="_blank" rel="noopener"
                               class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-external-link-alt mr-1"></i>Abrir en otra pestaña
                            </a>
                        </div>

                        @if($isPdf)
                            <div class="transfer-evidence-viewer">
                                <iframe src="{{ $evidenceUrl }}#toolbar=1&navpanes=0&view=FitH"
                                        title="Evidencia PDF de la transferencia {{ $transferData['folio'] ?? '' }}"
                                        width="100%" height="430" loading="eager"
                                        style="border: 0; border-radius: 6px;"></iframe>
                            </div>
                        @else
                            <div class="text-center transfer-evidence-viewer p-2">
                                <img src="{{ $evidenceUrl }}"
                                     alt="Evidencia de transferencia"
                                     onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');"
                                     style="max-width: 100%; max-height: 430px; object-fit: contain; border-radius: 6px;">
                                <div class="d-none alert alert-danger mb-0">
                                    No fue posible cargar la imagen. Usa “Abrir en otra pestaña” para consultarla.
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-danger mb-0">
                            El cliente aun no ha subido evidencia de transferencia.
                        </div>
                    @endif

                    <div class="form-group mt-3">
                        <label>Observaciones (solo para rechazo)</label>
                        <textarea wire:model.defer="transferValidationNote" class="form-control" rows="3" placeholder="Motivo de rechazo o notas internas..."></textarea>
                    </div>
                @else
                    <div class="text-center text-muted">
                        <h6>No hay venta seleccionada</h6>
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="requestCloseTransferValidationModal">Cerrar</button>
                <button type="button"
                        class="btn btn-danger"
                        wire:click="rejectTransfer"
                        wire:loading.attr="disabled"
                        wire:target="rejectTransfer"
                        {{ !$hasTransferSale || empty($transferData['transferencia_evidencia_path']) ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="rejectTransfer">
                        <i class="fas fa-times"></i> Rechazar
                    </span>
                    <span wire:loading wire:target="rejectTransfer">
                        <i class="fas fa-spinner fa-spin"></i> Procesando...
                    </span>
                </button>
                <button type="button"
                        class="btn btn-success"
                        wire:click="approveTransfer"
                        wire:loading.attr="disabled"
                        wire:target="approveTransfer"
                        {{ !$hasTransferSale || empty($transferData['transferencia_evidencia_path']) ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="approveTransfer">
                        <i class="fas fa-check"></i> Aceptar
                    </span>
                    <span wire:loading wire:target="approveTransfer">
                        <i class="fas fa-spinner fa-spin"></i> Procesando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .transfer-evidence-viewer {
        min-height: 220px;
        overflow: hidden;
        background: #eef0f4;
        border: 1px solid #d8dce5;
        border-radius: 7px;
    }
</style>
