 </div>
      <div class="modal-footer">

        <button type="button"
          wire:click="resetUI()"
          class="btn btn-outline-secondary close-btn"
          data-dismiss="modal"
          onclick="if(window.jQuery){$('#theModal').modal('hide');$('body').removeClass('modal-open');$('.modal-backdrop').remove();}">
          CERRAR
        </button>

        @if($selected_id < 1)
        <button type="button" wire:click.prevent="Store()" class="btn btn-dark close-modal js-btn-store">
          <span class="js-btn-store-label">GUARDAR</span>
        </button>
        @else
        <button type="button" wire:click.prevent="Update()" class="btn btn-dark close-modal js-btn-update">ACTUALIZAR</button>
        @endif


      </div>
    </div>
  </div>
</div>
