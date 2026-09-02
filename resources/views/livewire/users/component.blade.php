<div class="row sales layout-top-spacing">

    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{$componentName}} | {{ $pageTitle }}</b>
                </h4>
                <ul class="tabs tab-pills">
                    <li>
                        <a href="javascript:void(0)" class="tabmenu bg-dark" data-toggle="modal" data-target="#theModal">Agregar</a>
                    </li>
                </ul>
            </div>
            @include('common.searchbox')

            <div class="widget-content">

                <div class="table-responsive">
                    <table class="table table-bordered table striped mt-1">
                        <thead class="text-white" style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-white">USUARIO</th>
                                <th class="table-th text-center">TELÉFONO</th>
                                <th class="table-th text-center">EMAIL</th>
                                <th class="table-th text-center">ESTATUS</th>
                                <th class="table-th text-center">PERFIL</th>
                                <th class="table-th text-center">IMÁGEN</th>
                                <th class="table-th text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $r)
                            <tr>
                                <td><h6>{{$r->name}}</h6></td>
                                <td class="text-center"><h6>{{$r->phone}}</h6></td>
                                <td class="text-center"><h6>{{$r->email}}</h6></td>
                                <td class="text-center">
                                    <span class="badge {{ strtoupper($r->status) == 'ACTIVE' ? 'badge-success' : 'badge-danger' }} text-uppercase">{{ strtoupper($r->status) == 'ACTIVE' ? 'ACTIVO' : 'BLOQUEADO' }}</span>
                                </td>
                                <td class="text-center text-uppercase">
                                    <h6>{{$r->profile}}</h6>
                                    <small><b>Roles:</b>{{implode(',',$r->getRoleNames()->toArray())}}</small>
                                </td>

                                <td class="text-center">
                                    @if($r->image)
                                        @php
                                            $rawImage = (string) $r->image;
                                            $imageSrc = str_starts_with($rawImage, 'data:image')
                                                ? $rawImage
                                                : (str_contains($rawImage, 'users/')
                                                    ? \Illuminate\Support\Facades\Storage::url($rawImage)
                                                    : asset('storage/users/' . $rawImage));
                                        @endphp
                                        <img
                                            src="{{ $imageSrc }}"
                                            alt="Foto de {{ $r->name }}"
                                            style="width: 56px; height: 56px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb;"
                                        >
                                    @endif
                                </td>

                             <td class="text-center">
                                <a href="javascript:void(0)"
                                wire:click="edit({{$r->id}})"
                                class="btn btn-dark mtmobile" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if(Auth()->user()->id != $r->id)
                            <a href="javascript:void(0)"
                            onclick="Confirm('{{$r->id}}')"
                            class="btn btn-dark" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                        @endif


                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{$data->links()}}
    </div>

</div>


</div>


</div>

@include('livewire.users.form')
</div>


<script>
    document.addEventListener('DOMContentLoaded', function(){
        let isSavingUser = false

        const cleanModalOverlay = () => {
            $('body').removeClass('modal-open')
            $('.modal-backdrop').remove()
        }

        function getStoreButton() {
            return document.querySelector('#theModal .js-btn-store')
        }

        function setStoreLoadingState() {
            const btn = getStoreButton()
            if (!btn) return

            isSavingUser = true
            btn.disabled = true
            btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> GUARDANDO...'
        }

        function restoreStoreButton() {
            const btn = getStoreButton()
            if (!btn) {
                isSavingUser = false
                return
            }

            btn.disabled = false
            btn.innerHTML = '<span class="js-btn-store-label">GUARDAR</span>'
            isSavingUser = false
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#theModal .js-btn-store')
            if (!btn) return
            setStoreLoadingState()
        })

        window.livewire.on('user-added', Msg => {
            $('#theModal').modal('hide')
            resetInputFile()
            restoreStoreButton()
            noty(Msg)
        })
        window.livewire.on('user-updated', Msg => {
            $('#theModal').modal('hide')
            resetInputFile()
            restoreStoreButton()
            noty(Msg)
        })
        window.livewire.on('user-deleted', Msg => {
            noty(Msg)
        })
        window.livewire.on('hide-modal', Msg => {
            $('#theModal').modal('hide')
            restoreStoreButton()
        })
        window.livewire.on('show-modal', Msg => {
            $('#theModal').modal('show')
            restoreStoreButton()
        })
        window.livewire.on('user-withsales', Msg => {
            noty(Msg)
        })

        if (window.livewire && typeof window.livewire.hook === 'function') {
            window.livewire.hook('message.processed', () => {
                if (!isSavingUser) return

                const modalVisible = $('#theModal').hasClass('show')
                const hasValidationErrors = document.querySelector('#theModal .text-danger.er') !== null

                if (modalVisible && hasValidationErrors) {
                    restoreStoreButton()
                }
            })
        }

        $(document).on('click', '#theModal .close-btn', function () {
            $('#theModal').modal('hide')
            setTimeout(cleanModalOverlay, 120)
        })

        $(document).on('hidden.bs.modal', '#theModal', function () {
            cleanModalOverlay()
        })

    })

    function resetInputFile()
    {
        $('input[type=file]').val('');
    }


    function Confirm(id)
    {

        swal({
            title: 'CONFIRMAR',
            text: '¿CONFIRMAS ELIMINAR EL REGISTRO?',
            type: 'warning',
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            cancelButtonColor: '#fff',
            confirmButtonColor: '#3B3F5C',
            confirmButtonText: 'Aceptar'
        }).then(function(result) {
            if(result.value){
                window.livewire.emit('deleteRow', id)
                swal.close()
            }

        })
    }


</script>
