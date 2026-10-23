{{-- Audit trail for one device, employee or request. Expects $logs (collection of AuditLog).
     Compact layout (user and action stacked) so it fits narrow side columns. --}}
<div class="table-responsive">
    <table class="table log-table mb-0" style="min-width:520px">
        <thead class="table-light"><tr><th style="width:130px">When</th><th style="width:210px">Activity</th><th>Details</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            @php([$tone, $icon] = $log->action_style)
            <tr>
                <td><span class="log-time" title="{{ $log->created_at->format('M d, Y h:i:s A') }}">{{ $log->created_at->format('M d, Y') }}<small>{{ $log->created_at->format('h:i A') }}</small></span></td>
                <td>
                    <span class="who mb-1">
                        <span class="avatar-xs {{ $log->user ? '' : 'system' }}" aria-hidden="true">{{ $log->user ? Str::of($log->user->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : 'SY' }}</span>
                        {{ $log->user?->name ?? 'System' }}
                    </span>
                    <span class="action-pill text-bg-{{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $log->action_label }}</span>
                </td>
                <td class="log-desc" style="min-width:180px">{{ $log->description }}</td>
            </tr>
        @empty
            <tr><td colspan="3"><div class="empty-state py-3"><i class="bi bi-journal" aria-hidden="true"></i>No activity yet.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
