@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td style="padding-right: 12px;">
<table cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td width="42" height="42" align="center" valign="middle" bgcolor="#7c4df5" style="background-color: #7c4df5; border-radius: 13px; color: #ffffff; font-size: 19px; font-weight: 700; line-height: 42px;">{{ mb_strtoupper(mb_substr(trim($slot), 0, 1)) }}</td>
</tr>
</table>
</td>
<td valign="middle" style="color: #18181b; font-size: 21px; font-weight: 700; letter-spacing: -0.02em;">{!! $slot !!}</td>
</tr>
</table>
</a>
</td>
</tr>
