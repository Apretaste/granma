<h1>Categoria: {$category}</h1>

{foreach from=$articles item=article}
	<b>{link href="GRANMA HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{$article['description']|truncate:200:" ..."}<br/>
	<small><font color="gray">{$article['author']}, {$article['pubDate']|date_format}</font></small>
	{space15}
{foreachelse}
	<p>Lo siento, a&uacute;n no tenemos historias para esta categor&iacute;a :'-(</p>
{/foreach}

{space5}

<center>
	{button href="GRANMA" caption="Titulares"}
</center>
