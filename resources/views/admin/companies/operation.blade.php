<section id="modalidades" class="rounded-3xl border bg-white p-5">
    <h2 class="text-lg font-black">Modalidades</h2>
    <div class="mt-3 space-y-3">
        @forelse($organization->modalities as $modality)
            <article class="rounded-xl border p-3 text-sm">
                <strong>{{ $modality->label() }}</strong>
                <span>{{ ['requested' => 'Solicitada', 'approved' => 'Aprobada', 'rejected' => 'Rechazada'][$modality->status] ?? $modality->status }}</span>
                <p class="mt-1 text-slate-600">Permiso {{ $modality->permit_number ?: 'sin número' }} · vence {{ $modality->permit_expires_on?->format('d/m/Y') ?: 'sin fecha' }}</p>
                @if($modality->aoc_number)<p class="text-slate-600">AOC {{ $modality->aoc_number }} · vence {{ $modality->aoc_expires_on?->format('d/m/Y') ?: 'sin fecha' }}</p>@endif
                @if($modality->status === 'rejected' && $modality->rejection_reason)<p class="text-rose-800">{{ $modality->rejection_reason }}</p>@endif
                @if($modality->status === 'requested')
                    <form method="POST" action="{{ route('admin.companies.modalities.review', [$organization, $modality]) }}" class="mt-2 grid gap-2 sm:grid-cols-[160px_1fr_auto]">
                        @csrf @method('PATCH')
                        <select name="decision" class="rounded-lg border px-2 py-2" required><option value="approved">Aprobar</option><option value="rejected">Rechazar</option></select>
                        <input name="reason" required placeholder="Motivo" class="rounded-lg border px-2 py-2">
                        <button class="rounded-lg bg-emerald-800 px-3 py-2 font-semibold text-white">Guardar</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-500">Esta empresa todavía no tiene modalidades en el registro nuevo.</p>
        @endforelse
    </div>
</section>
<section id="documentos" class="rounded-3xl border bg-white p-5">
    <h2 class="text-lg font-black">Documentos</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse($organization->documents as $document)
            <li>{{ $document->type }} · {{ $document->reference_number ?: $document->original_name }} · vence {{ $document->expires_on?->format('d/m/Y') ?: 'sin fecha' }}</li>
        @empty
            <li class="text-slate-500">Sin documentos cargados.</li>
        @endforelse
    </ul>
</section>
<section id="sedes" class="rounded-3xl border bg-white p-5">
    <h2 class="text-lg font-black">Sedes</h2>
    <div class="mt-3 space-y-3">
        @forelse($organization->sites as $site)
            <article class="rounded-xl border p-3 text-sm">
                <strong>{{ $site->name }}</strong> · {{ ['dock' => 'Muelle', 'office' => 'Oficina', 'airfield' => 'Aeródromo'][$site->kind] ?? $site->kind }} · {{ $site->city }}
                <p class="text-slate-600">{{ $site->address }} @if($site->hidden_by_admin)· oculta: {{ $site->hidden_reason }}@endif</p>
                <form method="POST" action="{{ route('admin.companies.sites.visibility', [$organization, $site]) }}" class="mt-2 flex flex-wrap gap-2">
                    @csrf @method('PATCH')
                    <input type="hidden" name="hidden" value="{{ $site->hidden_by_admin ? '0' : '1' }}">
                    <input name="reason" required placeholder="Motivo" class="rounded-lg border px-2 py-2">
                    <button class="rounded-lg border px-3 py-2 font-semibold">{{ $site->hidden_by_admin ? 'Volver a permitir' : 'Ocultar en Agencias' }}</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-slate-500">Sin sedes.</p>
        @endforelse
    </div>
</section>
<section id="whatsapp" class="rounded-3xl border bg-white p-5">
    <h2 class="text-lg font-black">WhatsApp</h2>
    <p class="mt-2 text-sm">Boletería: {{ \App\Support\PhoneNumber::display($organization->whatsapp) ?: 'sin número válido' }}</p>
    <p class="text-sm">Carga: {{ \App\Support\PhoneNumber::display($organization->whatsapp_cargo) ?: 'usa boletería' }}</p>
    @if($organization->whatsapp_hidden_by_admin)<p class="mt-2 text-sm text-rose-800">Botón oculto. {{ $organization->whatsapp_hidden_reason }}</p>@endif
    <form method="POST" action="{{ route('admin.companies.whatsapp.visibility', $organization) }}" class="mt-3 flex flex-wrap gap-2">
        @csrf @method('PATCH')
        <input type="hidden" name="hidden" value="{{ $organization->whatsapp_hidden_by_admin ? '0' : '1' }}">
        <input name="reason" required placeholder="Motivo o reporte de fraude" class="rounded-lg border px-2 py-2">
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">{{ $organization->whatsapp_hidden_by_admin ? 'Mostrar botón' : 'Ocultar botón público' }}</button>
    </form>
</section>
