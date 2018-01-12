<table width="100%">
	<tr>
		<td><h1>Noticias de hoy</h1></td>
		<td align="right" valign="top">
			{button href="GRANMA BUSCAR" popup="true" size="small" desc="Inserte una palabra o frase a buscar" caption="&#10004; Buscar"}
		</td>
	</tr>
</table>

{foreach from=$articles item=article name=arts}
	<b>{link href="GRANMA HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']|truncate:200:" ..."}<br/>
	<small>
		<font color="gray">{$article['author']}, {$article['pubDate']|date_format}</font>
		<br/>
		Categor&iacute;as:
		{foreach from=$article['category'] item=category name=cats}
			{link href="GRANMA CATEGORIA {$category}" caption="{$category}"}
			{if not $smarty.foreach.cats.last}{separator}{/if}
		{/foreach}
	</small>
	{space15}
{foreachelse}
	<p>No hay art&iacute;culos que mostrar. Intente otra vez o realice otra b&uacute;squeda.</p>
{/foreach}
