{{-- Modal: Alerta Emergente --}}
<div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true" wire:ignore.self
	 data-backdrop="static" data-keyboard="false">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">

			<div class="modal-header" style="background:#f6a821; color:#fff;">
				<h5 class="modal-title" id="alertModalLabel">
					<i class="fas fa-bell mr-2"></i>
					{{ $alertId ? 'Editar Alerta Emergente' : 'Nueva Alerta Emergente' }}
				</h5>
			</div>

			<div class="modal-body">
				<div class="row">

					{{-- Título --}}
					<div class="col-md-8 form-group">
						<label class="alert-label">Título <span class="text-danger">*</span></label>
						<input type="text" class="form-control @error('alertTitulo') is-invalid @enderror"
							   wire:model.lazy="alertTitulo"
							   placeholder="Ej: ¡Oferta de fin de semana!">
						@error('alertTitulo')
							<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>

					{{-- Tipo --}}
					<div class="col-md-4 form-group">
						<label class="alert-label">Tipo <span class="text-danger">*</span></label>
						<select class="form-control @error('alertTipo') is-invalid @enderror"
								wire:model="alertTipo">
							<option value="oferta">Oferta</option>
							<option value="alerta">Alerta</option>
							<option value="novedad">Novedad</option>
							<option value="anuncio">Anuncio</option>
						</select>
						@error('alertTipo')
							<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>

					{{-- Descripción --}}
					<div class="col-12 form-group">
						<label class="alert-label">Descripción</label>
						<textarea class="form-control"
								  wire:model.lazy="alertDescripcion"
								  rows="3"
								  placeholder="Descripción detallada de la alerta..."></textarea>
					</div>

					{{-- Imagen --}}
					<div class="col-md-6 form-group">
						<label class="alert-label">Imagen</label>
						<input type="file" class="form-control-file @error('alertImagen') is-invalid @enderror"
							   wire:model="alertImagen" accept="image/*">
						@error('alertImagen')
							<div class="invalid-feedback d-block">{{ $message }}</div>
						@enderror
						@if ($alertImagenActual && ! $alertImagen)
							<div class="mt-2">
								<img src="{{ asset('storage/' . $alertImagenActual) }}"
									 alt="Imagen actual" class="alert-img-preview">
								<small class="text-muted d-block">Imagen actual</small>
							</div>
						@endif
						@if ($alertImagen)
							<div class="mt-2">
								<img src="{{ $alertImagen->temporaryUrl() }}"
									 alt="Preview" class="alert-img-preview">
								<small class="text-success d-block">Vista previa</small>
							</div>
						@endif
					</div>

					{{-- Enlace --}}
					<div class="col-md-6">
						<div class="form-group">
							<label class="alert-label">URL del enlace</label>
							<input type="url" class="form-control @error('alertLinkUrl') is-invalid @enderror"
								   wire:model.lazy="alertLinkUrl"
								   placeholder="https://...">
							@error('alertLinkUrl')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>
						<div class="form-group">
							<label class="alert-label">Texto del enlace</label>
							<input type="text" class="form-control"
								   wire:model.lazy="alertLinkTexto"
								   placeholder="Ej: Ver oferta">
						</div>
					</div>

					{{-- Programación --}}
					<div class="col-12 mt-1">
						<h6 class="alert-section-title"><i class="fas fa-calendar-alt mr-1"></i> Programación</h6>
						<hr class="mt-1 mb-3">
					</div>

					<div class="col-md-6 form-group">
						<label class="alert-label">Fecha y hora de inicio</label>
						<input type="datetime-local" class="form-control @error('alertFechaInicio') is-invalid @enderror"
							   wire:model.lazy="alertFechaInicio">
						<small class="text-muted">Dejar vacío para mostrar inmediatamente.</small>
						@error('alertFechaInicio')
							<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>

					<div class="col-md-3 form-group">
						<label class="alert-label">Duración (días) <span class="text-danger">*</span></label>
						<input type="number" class="form-control @error('alertDiasDuracion') is-invalid @enderror"
							   wire:model.lazy="alertDiasDuracion"
							   min="1" step="1">
						@error('alertDiasDuracion')
							<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>

					<div class="col-md-3 form-group d-flex flex-column justify-content-end pb-3">
						<div class="custom-control custom-switch">
							<input type="checkbox" class="custom-control-input"
								   id="alertActivoSwitch"
								   wire:model="alertActivo">
							<label class="custom-control-label font-weight-bold" for="alertActivoSwitch">
								{{ $alertActivo ? 'Activa' : 'Inactiva' }}
							</label>
						</div>
					</div>

				</div>
			</div>{{-- /modal-body --}}

			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">
					<i class="fas fa-times mr-1"></i> Cancelar
				</button>
				<button type="button" class="btn btn-warning text-white"
						wire:click="saveAlert" wire:loading.attr="disabled">
					<span wire:loading wire:target="saveAlert">
						<span class="spinner-border spinner-border-sm mr-1" role="status"></span>
						Guardando...
					</span>
					<span wire:loading.remove wire:target="saveAlert">
						<i class="fas fa-save mr-1"></i>
						{{ $alertId ? 'Actualizar' : 'Guardar' }}
					</span>
				</button>
			</div>

		</div>
	</div>
</div>

<style>
	.alert-label { font-size: .83rem; font-weight: 600; color: #3B3F5C; margin-bottom: 4px; }
	.alert-section-title { color: #3B3F5C; font-weight: 700; font-size: .9rem; }
	.alert-img-preview { max-height: 80px; max-width: 140px; object-fit: cover; border: 1px solid #dee2e6; border-radius: 6px; }
</style>
