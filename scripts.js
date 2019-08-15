$(document).ready(function(){
	$('.materialboxed').materialbox();
	$('.modal').modal();
});

function sendSearch() {
	var query = $('#searchQuery').val().trim();

	if(query.length <= 5) {
		M.toast({html: 'Mínimo 2 caracteres'});
		return false;
	}

	apretaste.send({
		'command':'GRANMA BUSCAR',
		'data':{'searchQuery':query, 'isCategory':false}
	});
}
