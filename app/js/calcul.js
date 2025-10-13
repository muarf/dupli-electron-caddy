var idsToUpdate = [], tableMachine
function calculateTotal() {
  var total = 0;
  var checkboxes = document.getElementsByName("chkbox[]");

  for (var i = 0; i < checkboxes.length; i++) {
    if (checkboxes[i].checked) {
       idsToUpdate.push(checkboxes[i].dataset.id);
       total += parseFloat(checkboxes[i].value);
       tableMachine = checkboxes[i].dataset.machine;

    }
  }
  document.getElementById("total").innerHTML = total;
  $("#myModal").modal("show");
}
function pay(event) {
  //event.preventDefault();
  const data = { ids: idsToUpdate, table: tableMachine };
  const url = window.location.href;

  console.log("Données envoyées en POST: ", data);

  $.ajax({
    type: "POST",
    url: url,
    contentType: "application/x-www-form-urlencoded; charset=UTF-8",
    headers: { 'X-Requested-With': 'XMLHttpRequest'},
    data: data,
    success: function(response) {
      //console.log("Données POST envoyées: ", response);
      if (response.status == "success") {
        console.log("Mise à jour effectuée avec succès");
        alert("Mise à jour effectuée avec succès");
        window.location.reload();
       
        // recharger la page ou une partie de la page
      } else {
        console.log("Une erreur s'est produite lors de la mise à jour");
        alert("Une erreur s'est produite lors de la mise à jour");
        window.location.reload();
      }
    },
    error: function(jqXHR, textStatus, errorThrown) {
      console.error(textStatus, errorThrown);
    }
  });

  console.log("Modal masqué");
  //$("#myModal").modal("hide");
  $('#myModal').on('hidden.bs.modal', function (e) {
    window.location.reload(); // Recharge la page
  })
}


/*function pay() {
  $.ajax({
    type: "POST",
    url: window.location.href,
    contentType: "application/x-www-form-urlencoded; charset=UTF-8",
    headers: { 'X-Requested-With': 'XMLHttpRequest'},
    data: { ids: idsToUpdate, table: tableMachine },
    success: function(response) {
      if (response == "success") {
        //traitement en cas de succes
        alert("Mise à jour effectuée avec succès");
        // recharger la page ou une partie de la page
      } else {
        //traitement en cas d'erreur
        alert("Une erreur s'est produite lors de la mise à jour");
      }
    },
    error: function(xhr, status, error) {
      console.error("Erreur AJAX: ", status, error);
    }
  })
  .done(function(data) {
    console.log("Données POST envoyées: ", data);
  });
  $("#myModal").modal("hide");
}*/
function closeModal(){
   $("#myModal").modal("hide");
}