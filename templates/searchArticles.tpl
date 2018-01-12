<h1>Buscar: {$search|ucfirst}</h1>

{foreach from=$articles item=article name=arts}
	<small><font color="gray">{$article['pubDate']|date_format|capitalize}</font></small><br/>
	<b>{link href="GRANMA HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{$article['description']|truncate:200:" ..."}<br/>
	{space15}
{foreachelse}
	<p>No hay art&iacute;culos que mostrar. Intente otra vez o realice otra b&uacute;squeda.</p>
{/foreach}

{space5}

<center>
	{button href="GRANMA" caption="Titulares"}
</center>