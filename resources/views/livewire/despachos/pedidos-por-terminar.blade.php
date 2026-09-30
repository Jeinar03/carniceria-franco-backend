<div class="dropdown zero-stock-header mr-3" wire:poll.30s wire:ignore.self>
    <button class="btn btn-outline-{{ $color }} btn-sm zero-stock-trigger" type="button"
            id="pendingOrdersDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            title="Pedidos por terminar" wire:ignore.self>
        <i class="fas fa-clock"></i>
        <span class="zero-stock-label ml-1">Por terminar</span>
        <span class="badge badge-{{ $color }} ml-1">{{ $total }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-right zero-stock-dropdown-menu" aria-labelledby="pendingOrdersDropdown" wire:ignore.self>
        <div class="zero-stock-dropdown-title">
            <div>
                <strong>Pedidos por terminar</strong>
                <small class="d-block text-muted">
                    @if ($masViejoTexto)
                        El mas viejo lleva {{ $masViejoTexto }}
                    @else
                        No hay pedidos pendientes
                    @endif
                </small>
            </div>
            <span class="badge badge-{{ $color }}">{{ $total }}</span>
        </div>
        <div class="zero-stock-scroll">
            @forelse ($listado as $pedido)
                <a href="{{ url('clientes/despachos') }}" class="dropdown-item zero-stock-item">
                    <span class="zero-stock-icon"><i class="fas fa-receipt"></i></span>
                    <span class="zero-stock-info">
                        <strong>{{ $pedido['folio'] }}</strong>
                        <small class="d-block text-muted">
                            {{ $pedido['cliente'] }} - {{ $pedido['estado'] }}
                            @if ($pedido['transferencia_por_validar'])
                                <span class="badge badge-info">Transferencia por validar</span>
                            @endif
                        </small>
                    </span>
                    <span class="badge badge-{{ $pedido['color'] }} ml-2">{{ $pedido['tiempo'] }}</span>
                </a>
            @empty
                <div class="dropdown-item-text text-muted p-3">Todo al dia, no hay pedidos por terminar.</div>
            @endforelse
            @if ($total > $listado->count())
                <div class="dropdown-item-text text-muted small px-3 pb-2">y {{ $total - $listado->count() }} mas en Despachos</div>
            @endif
        </div>
    </div>
</div>
