<x-mail::message>
# Ciao {{ $invited->name }},

<x-mail::panel>
**{{ $inviter->name }}** ti ha aggiunto a **«{{ $resource->name }}»**. Da ora hai accesso e puoi aggiungere, modificare ed eliminare come chi ti ha invitato.
</x-mail::panel>

**Cosa:** {{ $resource->label }}  
**Chi ti ha invitato:** {{ $inviter->name }}

<x-mail::button :url="$url">
Apri {{ mb_strtolower($resource->label) }}
</x-mail::button>

Se non ti aspettavi questo invito puoi ignorare l'email: fino a quando non ci entri non cambia nulla per te.

A presto,<br>
Il team {{ config('app.name') }}
</x-mail::message>
