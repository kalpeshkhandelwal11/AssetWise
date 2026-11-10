{{-- Six signature blocks from the NHAI checklist; names pre-filled, lines left for wet signatures. --}}
<table class="sign" style="margin-top: 16px;">
    @foreach(array_chunk(\App\Models\PlazaTakeover::SIGNATORIES, 3, true) as $row)
        <tr>
            @foreach($row as $key => $label)
                <td>
                    <div class="line">
                        {{ $label }}<br>
                        <span class="muted">{{ $signatories[$key] ?? '' }}</span>
                    </div>
                </td>
            @endforeach
        </tr>
    @endforeach
</table>
