/**
 * tirage-multimachines.js
 *
 * Logique du formulaire de tirage multi-machines.
 * Extrait de app/view/tirage_multimachines.html.php
 *
 * Dépendances :
 *   - CONFIG (objet injecté par le PHP via json_encode) avec :
 *       .prix_data          – tableau des prix depuis la BDD
 *       .duplicopieur_id    – ID du duplicopieur sélectionné (défaut)
 *       .machine_price_mappings – mappings machine → price_key
 *       .strings            – chaînes traduites
 *   - jQuery ($, $.get, $.fn)
 *   - showAppModal() (composant global)
 */
(function () {
  'use strict';

  const prixData = CONFIG.prix_data || {};
  const defaultDuplicopieurId = CONFIG.duplicopieur_id || '';
  const S = CONFIG.strings || {};

  let machineCount = 1;

  // =========================================================================
  // SAUVEGARDE / RESTAURATION DES DONNÉES DU FORMULAIRE
  // =========================================================================

  function saveFormData() {
    const form = document.getElementById('multimachines-form');
    if (!form) {
      console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.formulaire_multimachines_form'] || 'Formulaire multimachines-form non trouvé - probablement sur la page de confirmation'));
      return;
    }

    try {
      const formData = new FormData(form);
      const data = {};

      for (let [key, value] of formData.entries()) {
        if (data[key]) {
          if (!Array.isArray(data[key])) {
            data[key] = [data[key]];
          }
          data[key].push(value);
        } else {
          data[key] = value;
        }
      }

      form.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(input => {
        const name = input.name;
        if (input.type === 'checkbox') {
          if (!data[name]) data[name] = [];
          if (input.checked && !data[name].includes(input.value)) {
            data[name].push(input.value);
          }
        } else if (input.type === 'radio' && input.checked) {
          data[name] = input.value;
        }
      });

      const machineIndicesInForm = new Set();
      form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
        const match = input.name.match(/machines\[(\d+)\]/);
        if (match) {
          machineIndicesInForm.add(parseInt(match[1]));
        }
      });
      const maxIndex = machineIndicesInForm.size > 0 ? Math.max(...machineIndicesInForm) : 0;
      const machineCountFromIndices = maxIndex + 1;

      const machineItems = form.querySelectorAll('.machine-item, [class*="machine-item"]').length;
      const machinePanels = form.querySelectorAll('[id^="duplicopieur-interface-"], [id^="photocopieur-interface-"]').length;

      data['_machine_count'] = Math.max(machineCountFromIndices, machineItems, machinePanels, machineCount, 1);

      console.log('💾 Sauvegarde - Nombre de machines détecté:', data['_machine_count'], {
        machineCountFromIndices,
        machineItems,
        machinePanels,
        machineCount,
        indices: Array.from(machineIndicesInForm)
      });

      data['_ui_state'] = {};
      form.querySelectorAll('[id*="interface"]').forEach(el => {
        data['_ui_state'][el.id] = el.style.display !== 'none';
      });

      sessionStorage.setItem('tirage_multimachines_form_data', JSON.stringify(data));
      console.log('✅ Données du formulaire sauvegardées');
    } catch (e) {
      console.error('❌ Erreur lors de la sauvegarde:', e);
    }
  }

  function addMachineAsync(index) {
    return new Promise((resolve, reject) => {
      const container = document.getElementById('machines-container');
      if (!container) {
        reject('Container machines-container non trouvé');
        return;
      }

      fetch(`?get-machine-template&index=${index}`)
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            reject(data.error);
            return;
          }

          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = data.html;
          const newMachineContainer = tempDiv.firstElementChild;

          if (!newMachineContainer) {
            reject((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.aucun__l_ment_trouv__dans_le_h'] || 'Aucun élément trouvé dans le HTML généré'));
            return;
          }

          const addButtonContainer = document.getElementById('buttons-container');

          if (addButtonContainer && container.contains(addButtonContainer)) {
            container.insertBefore(newMachineContainer, addButtonContainer);
          } else {
            container.appendChild(newMachineContainer);
          }

          machineCount = Math.max(machineCount, index + 1);

          const removeBtn = newMachineContainer.querySelector('.remove-machine');
          if (removeBtn) {
            removeBtn.addEventListener('click', function () {
              newMachineContainer.remove();
              machineCount = Math.max(1, machineCount - 1);
              calculateTotalPrice();
              saveFormData();
            });
          }

          setTimeout(() => {
            try {
              toggleMachineType(index);

              const duplicopieurIdField = document.querySelector(`select[name="machines[${index}][duplicopieur_id]"]`) || document.querySelector(`input[name="machines[${index}][duplicopieur_id]"]`);
              if (duplicopieurIdField && duplicopieurIdField.value) {
                const duplicopieurId = duplicopieurIdField.value;
                if (typeof updateDuplicopieurCounters === 'function') {
                  updateDuplicopieurCounters(duplicopieurId, index);
                } else if (typeof loadTamboursForDuplicopieur === 'function') {
                  loadTamboursForDuplicopieur(duplicopieurId, index);
                }
              }

              console.log(`✅ Machine ${index} initialisée complètement`);
              resolve(newMachineContainer);
            } catch (e) {
              console.error(`❌ Erreur lors de l'initialisation de la machine ${index}:`, e);
              resolve(newMachineContainer);
            }
          }, 150);
        })
        .catch(error => {
          reject(error);
        });
    });
  }

  function restoreFormData() {
    const saved = sessionStorage.getItem('tirage_multimachines_form_data');
    if (!saved) {
      console.log('Aucune donnée sauvegardée à restaurer');
      return false;
    }

    const form = document.getElementById('multimachines-form');
    if (!form) {
      console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.formulaire_non_trouv__pour_res'] || 'Formulaire non trouvé pour restauration'));
      return false;
    }

    try {
      const data = JSON.parse(saved);
      console.log('🔄 Restauration des données du formulaire...');

      const savedMachineIndices = new Set();
      Object.keys(data).forEach(key => {
        if (key.startsWith('_')) return;
        const match = key.match(/machines\[(\d+)\]/);
        if (match) {
          savedMachineIndices.add(parseInt(match[1]));
        }
      });

      const savedIndicesArray = Array.from(savedMachineIndices).sort((a, b) => a - b);
      const maxMachineIndex = savedIndicesArray.length > 0 ? Math.max(...savedIndicesArray) : 0;

      const existingMachineIndices = new Set();
      form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
        const match = input.name.match(/machines\[(\d+)\]/);
        if (match) {
          existingMachineIndices.add(parseInt(match[1]));
        }
      });
      const existingIndicesArray = Array.from(existingMachineIndices).sort((a, b) => a - b);

      const missingIndices = savedIndicesArray.filter(idx => !existingMachineIndices.has(idx));

      console.log(`🔍 Machines sauvegardées: indices ${savedIndicesArray.join(', ')}`, {
        savedIndices: savedIndicesArray,
        existingIndices: existingIndicesArray,
        missingIndices: missingIndices,
        maxIndex: maxMachineIndex
      });

      const restoreFields = () => {
        console.log('🔄 Début de la restauration des champs...');
        let restoredCount = 0;
        let missingCount = 0;

        Object.keys(data).forEach(key => {
          if (key.startsWith('_')) return;

          const inputs = form.querySelectorAll(`[name="${key}"]`);
          if (inputs.length === 0) {
            const brochureMatch = key.match(/machines\[(\d+)\]\[brochures\]\[(\d+)\]\[(\w+)\]/);
            if (brochureMatch) {
              missingCount++;
              return;
            }
            if (!key.includes('brochures')) {
              console.log(`⚠️ Champ non trouvé: ${key}`);
              missingCount++;
            }
            return;
          }

          inputs.forEach(input => {
            try {
              if (input.type === 'checkbox') {
                const value = Array.isArray(data[key]) ? data[key] : [data[key]];
                input.checked = value.includes(input.value);
              } else if (input.type === 'radio') {
                input.checked = input.value === data[key];
              } else {
                input.value = data[key];
              }
              restoredCount++;
            } catch (e) {
              console.error(`❌ Erreur lors de la restauration de ${key}:`, e);
            }
          });
        });

        console.log(`✅ Champs restaurés: ${restoredCount}, champs manquants: ${missingCount}`);

        if (data['_ui_state']) {
          Object.keys(data['_ui_state']).forEach(id => {
            const el = document.getElementById(id);
            if (el) {
              el.style.display = data['_ui_state'][id] ? '' : 'none';
            }
          });
        }

        form.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
          const name = radio.name;
          const match = name.match(/machines\[(\d+)\]\[type\]/);
          if (match) {
            const index = match[1];
            setTimeout(() => {
              toggleMachineType(index);
              const duplicopieurSelect = document.querySelector(`select[name="machines[${index}][duplicopieur_id]"]`);
              if (duplicopieurSelect && duplicopieurSelect.value) {
                if (typeof updateDuplicopieurCounters === 'function') {
                  updateDuplicopieurCounters(duplicopieurSelect.value, index);
                } else if (typeof loadTamboursForDuplicopieur === 'function') {
                  loadTamboursForDuplicopieur(duplicopieurSelect.value, index);
                }
                const event = new Event('change', { bubbles: true });
                duplicopieurSelect.dispatchEvent(event);
              }
            }, 50);
          }

          const modeMatch = name.match(/machines\[(\d+)\]\[mode_saisie\]/);
          if (modeMatch) {
            const index = modeMatch[1];
            setTimeout(() => toggleSaisieMode(index), 50);
          }
        });

        form.querySelectorAll('select').forEach(select => {
          if (select.value) {
            const event = new Event('change', { bubbles: true });
            setTimeout(() => {
              try {
                select.dispatchEvent(event);
              } catch (e) {
                console.error('❌ Erreur lors du déclenchement de l\'événement change:', e);
              }
            }, 100);
          }
        });

        form.querySelectorAll('input[id^="couleur_"][type="checkbox"]').forEach(checkbox => {
          const match = checkbox.id.match(/couleur_(\d+)_\d+/);
          if (match) {
            const machineIndex = match[1];
            if (checkbox.checked) {
              setTimeout(() => {
                if (typeof toggleFillRateDisplay === 'function') {
                  toggleFillRateDisplay(machineIndex);
                }
              }, 100);
            }
          }
        });

        setTimeout(() => {
          if (typeof calculateTotalPrice === 'function') {
            console.log('💰 Recalcul du prix total...');
            calculateTotalPrice();
          }
        }, 500);

        console.log('✅ Restauration des données terminée');
      };

      if (missingIndices.length > 0) {
        console.log(`🔨 Création de ${missingIndices.length} machine(s) manquante(s) avec indices: ${missingIndices.join(', ')}...`);

        const createMachinesSequentially = async () => {
          for (const machineIndex of missingIndices) {
            try {
              console.log(`🔨 Création machine avec index ${machineIndex}...`);
              await addMachineAsync(machineIndex);
              console.log(`✅ Machine ${machineIndex} créée et initialisée`);
              await new Promise(resolve => setTimeout(resolve, 300));
            } catch (error) {
              console.error(`❌ Erreur lors de la création de la machine ${machineIndex}:`, error);
            }
          }

          const finalIndices = new Set();
          form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
            const match = input.name.match(/machines\[(\d+)\]/);
            if (match) {
              finalIndices.add(parseInt(match[1]));
            }
          });
          console.log(`🔍 Vérification finale: machines avec indices ${Array.from(finalIndices).sort((a, b) => a - b).join(', ')}`);

          console.log('✅ Toutes les machines créées, restauration des données...');
          setTimeout(restoreFields, 600);
        };

        createMachinesSequentially();
      } else {
        console.log('✅ Toutes les machines sont déjà présentes, restauration directe...');
        restoreFields();
      }

      return true;
    } catch (e) {
      console.error('❌ Erreur lors de la restauration:', e);
      return false;
    }
  }

  function returnToForm() {
    window.location.href = '?tirage_multimachines&retour=1';
  }

  function initAutoSave() {
    const form = document.getElementById('multimachines-form');
    if (!form) return;

    form.addEventListener('input', function () {
      clearTimeout(window.autoSaveTimeout);
      window.autoSaveTimeout = setTimeout(saveFormData, 500);
    });

    form.addEventListener('change', function () {
      saveFormData();
    });

    form.addEventListener('submit', function () {
      saveFormData();
    });

    console.log('✅ Auto-sauvegarde activée');
  }

  // =========================================================================
  // CALCUL DES PRIX
  // =========================================================================

  function findMachinePriceKey(machineName) {
    console.log('🔍 Recherche de la clé pour la machine:', machineName);

    for (const key in prixData) {
      if (key.startsWith('photocop_')) {
        console.log('🔍 Clé trouvée:', key);
      }
    }

    if (window.machinePriceCache && window.machinePriceCache[machineName]) {
      const priceKey = window.machinePriceCache[machineName];
      console.log(`🔑 Clé depuis le cache pour ${machineName}: ${priceKey}`);
      return priceKey;
    }

    for (const key in prixData) {
      if (key.startsWith('photocop_') && prixData[key]) {
        console.log('🔍 Utilisation de la clé de fallback:', key);
        return key;
      }
    }

    console.log('❌ Aucune clé trouvée pour:', machineName);
    return null;
  }

  function toggleSaisieMode(machineIndex) {
    var compteursRadio = document.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"][value="compteurs"]`);
    var manuelRadio = document.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"][value="manuel"]`);
    var compteursMode = document.getElementById(`compteurs-mode-${machineIndex}`);
    var manuelMode = document.getElementById(`manuel-mode-${machineIndex}`);

    if (compteursRadio.checked) {
      compteursMode.style.display = '';
      manuelMode.style.display = 'none';
    } else if (manuelRadio.checked) {
      compteursMode.style.display = 'none';
      manuelMode.style.display = '';
    }

    calculateTotalPrice();
  }

  function toggleMachineType(machineIndex) {
    var duplicopieurRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"][value="duplicopieur"]`);
    var photocopieurRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"][value="photocopieur"]`);
    var duplicopieurInterface = document.getElementById(`duplicopieur-interface-${machineIndex}`);
    var photocopieurInterface = document.getElementById(`photocopieur-interface-${machineIndex}`);
    var duplicopieurSelect = document.querySelector(`select[name="machines[${machineIndex}][duplicopieur_id]"]`);

    if (!duplicopieurRadio || !photocopieurRadio || !duplicopieurInterface || !photocopieurInterface) {
      console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.l_ments_manquants_pour_toggle'] || 'Éléments manquants pour toggleMachineType:'), {
        machineIndex: machineIndex,
        duplicopieurRadio: !!duplicopieurRadio,
        photocopieurRadio: !!photocopieurRadio,
        duplicopieurInterface: !!duplicopieurInterface,
        photocopieurInterface: !!photocopieurInterface
      });
      return;
    }

    if (duplicopieurRadio.checked) {
      if (duplicopieurSelect) {
        duplicopieurSelect.required = true;
      }
      duplicopieurInterface.style.display = 'block';
      photocopieurInterface.style.display = 'none';

      var duplicopieurFields = duplicopieurInterface.querySelectorAll('input, select, textarea');
      duplicopieurFields.forEach(function (field) {
        field.disabled = false;
      });

      var photocopFields = photocopieurInterface.querySelectorAll('input, select, textarea');
      photocopFields.forEach(function (field) {
        field.disabled = true;
        field.removeAttribute('required');
      });

    } else if (photocopieurRadio.checked) {
      if (duplicopieurSelect) {
        duplicopieurSelect.required = false;
        duplicopieurSelect.value = '';
      }
      duplicopieurInterface.style.display = 'none';
      photocopieurInterface.style.display = 'block';

      var duplicopieurFields = duplicopieurInterface.querySelectorAll('input, select, textarea');
      duplicopieurFields.forEach(function (field) {
        field.disabled = true;
        field.removeAttribute('required');
      });

      var photocopFields = photocopieurInterface.querySelectorAll('input, select, textarea');
      photocopFields.forEach(function (field) {
        field.disabled = false;
      });

      var requiredFields = photocopieurInterface.querySelectorAll('input[name*="[nb_exemplaires]"], input[name*="[nb_feuilles]"]');
      requiredFields.forEach(function (field) {
        field.setAttribute('required', 'required');
      });

      var exemplairesInput = photocopieurInterface.querySelector('input[name*="[nb_exemplaires]"]');
      var feuillesInput = photocopieurInterface.querySelector('input[name*="[nb_feuilles]"]');

      if (exemplairesInput && feuillesInput) {
        exemplairesInput.addEventListener('input', updateTotalFeuilles);
        feuillesInput.addEventListener('input', updateTotalFeuilles);
      }
    }

    calculateTotalPrice();
    updatePanelPreview(machineIndex);
    updateTotalFeuillesForMachine(machineIndex);
  }

  function updateTotalFeuilles() {
    var machineIndex = this.closest('[data-index]').getAttribute('data-index');
    updateTotalFeuillesForMachine(machineIndex);
  }

  function updateTotalFeuillesForMachine(machineIndex) {
    var brochures = document.querySelectorAll(`[data-index="${machineIndex}"] .brochure-item`);

    brochures.forEach(function (brochure, brochureIndex) {
      var exemplairesInput = brochure.querySelector('input[name*="[nb_exemplaires]"]');
      var feuillesInput = brochure.querySelector('input[name*="[nb_feuilles]"]');
      var totalSpan = document.getElementById(`total-feuilles-${machineIndex}-${brochureIndex}`);

      if (exemplairesInput && feuillesInput && totalSpan) {
        var exemplaires = parseInt(exemplairesInput.value) || 0;
        var feuilles = parseInt(feuillesInput.value) || 0;
        var total = exemplaires * feuilles;

        if (total > 0) {
          totalSpan.textContent = total + (total > 1 ? ' feuilles' : ' feuille');
          totalSpan.style.color = '#007bff';
        } else {
          totalSpan.textContent = '0 feuille';
          totalSpan.style.color = '#dc3545';
        }
      }
    });
  }

  function calculateMachinePrice(machineIndex) {
    console.log("🔍 calculateMachinePrice appelé avec index:", machineIndex);
    var machineElement = document.querySelector(`[data-index="${machineIndex}"]`);
    console.log("🔍 machineElement trouvé:", machineElement ? "oui" : "non");
    if (!machineElement) {
      console.log("❌ ERREUR: machineElement non trouvé pour index", machineIndex);
      return 0;
    }

    var typeRadio = machineElement.querySelector(`input[name="machines[${machineIndex}][type]"]:checked`);
    console.log("🔍 typeRadio trouvé:", typeRadio ? typeRadio.value : "non");
    if (!typeRadio) {
      console.log("❌ ERREUR: typeRadio non trouvé pour index", machineIndex);
      return 0;
    }

    var price = 0;
    var detailCalcul = '';

    if (typeRadio.value === 'duplicopieur') {
      console.log("🔍 Calcul duplicopieur pour index:", machineIndex);
      var modeSaisieRadio = machineElement.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"]:checked`);
      console.log("🔍 modeSaisieRadio trouvé:", modeSaisieRadio ? modeSaisieRadio.value : "non");
      var nbMasters = 0;
      var nbPassages = 0;

      if (modeSaisieRadio && modeSaisieRadio.value === 'compteurs') {
        var masterAvElement = machineElement.querySelector(`#master_av_${machineIndex}`);
        var masterApElement = machineElement.querySelector(`#master_ap_${machineIndex}`);
        var passageAvElement = machineElement.querySelector(`#passage_av_${machineIndex}`);
        var passageApElement = machineElement.querySelector(`#passage_ap_${machineIndex}`);

        console.log("machineElement.innerHTML:", machineElement.innerHTML.substring(0, 300) + '...');
        console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.recherche_des__l_ments_avec_id'] || "Recherche des éléments avec ID:"), {
          masterAv: `#master_av_${machineIndex}`,
          masterAp: `#master_ap_${machineIndex}`,
          passageAv: `#passage_av_${machineIndex}`,
          passageAp: `#passage_ap_${machineIndex}`
        });

        console.log("Éléments trouvés:", {
          masterAv: masterAvElement ? "oui" : "non",
          masterAp: masterApElement ? "oui" : "non",
          passageAv: passageAvElement ? "oui" : "non",
          passageAp: passageApElement ? "oui" : "non"
        });

        var masterAv = parseFloat(masterAvElement ? masterAvElement.value : 0) || 0;
        var masterAp = parseFloat(masterApElement ? masterApElement.value : 0) || 0;
        var passageAv = parseFloat(passageAvElement ? passageAvElement.value : 0) || 0;
        var passageAp = parseFloat(passageApElement ? passageApElement.value : 0) || 0;

        console.log("🔍 Valeurs brutes des champs:", {
          masterAvElement_value: masterAvElement ? masterAvElement.value : "élément non trouvé",
          masterApElement_value: masterApElement ? masterApElement.value : "élément non trouvé",
          passageAvElement_value: passageAvElement ? passageAvElement.value : "élément non trouvé",
          passageApElement_value: passageApElement ? passageApElement.value : "élément non trouvé"
        });

        nbMasters = Math.max(0, masterAp - masterAv);
        nbPassages = Math.max(0, passageAp - passageAv);

        console.log("🔍 Valeurs calculées:", {
          masterAv: masterAv,
          masterAp: masterAp,
          passageAv: passageAv,
          passageAp: passageAp,
          nbMasters: nbMasters,
          nbPassages: nbPassages
        });
      } else {
        nbMasters = parseFloat(machineElement.querySelector(`#nb_masters_${machineIndex}`).value) || 0;
        nbPassages = parseFloat(machineElement.querySelector(`#nb_passages_${machineIndex}`).value) || 0;
      }

      var nb_f = nbPassages;
      var rv = machineElement.querySelector(`input[name="machines[${machineIndex}][rv]"]`).checked;
      var feuilles_payees = machineElement.querySelector(`input[name="machines[${machineIndex}][feuilles_payees]"]`) ? machineElement.querySelector(`input[name="machines[${machineIndex}][feuilles_payees]"]`).checked : false;
      var a4 = machineElement.querySelector(`input[name="machines[${machineIndex}][A4]"]`).checked;

      if (rv) nb_f = nbPassages / 2;
      if (feuilles_payees) nb_f = 0;

      var taille = 'A3';
      var a4 = machineElement.querySelector(`input[name="machines[${machineIndex}][A4]"]`).checked;
      if (a4) taille = 'A4';

      var duplicopieurSelect = machineElement.querySelector('select[name*="[duplicopieur_id]"]');
      var duplicopieurId = duplicopieurSelect ? duplicopieurSelect.value : defaultDuplicopieurId;
      var machineKey = 'dupli_' + duplicopieurId;
      var prixMaster = prixData[machineKey] && prixData[machineKey]['master'] ? prixData[machineKey]['master']['unite'] : 0;

      var tambourSelect = machineElement.querySelector('select[name*="[tambour]"]');
      var tambourSelected = tambourSelect ? tambourSelect.value : '';
      var prixPassage = 0;

      console.log('🔍 Calcul prix passage - machineKey:', machineKey, 'tambourSelected:', tambourSelected);
      console.log('🔍 prixData[machineKey]:', prixData[machineKey]);

      if (tambourSelected && prixData[machineKey] && prixData[machineKey][tambourSelected]) {
        prixPassage = prixData[machineKey][tambourSelected]['unite'] || 0;
        console.log('✅ Prix passage (tambour sélectionné):', prixPassage);
      } else if (prixData[machineKey] && prixData[machineKey]['tambour_noir']) {
        prixPassage = prixData[machineKey]['tambour_noir']['unite'] || 0;
        console.log('✅ Prix passage (tambour noir fallback):', prixPassage);
      } else {
        console.log('❌ Aucun prix trouvé pour machineKey:', machineKey);
      }

      var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;

      if (taille === 'A4') {
        prixMaster = prixMaster / 2;
        prixPassage = prixPassage / 2;
      }

      console.log("Prix calculés:", {
        taille: taille,
        machineKey: machineKey,
        prixMaster: prixMaster,
        prixPassage: prixPassage,
        prixPapier: prixPapier
      });

      var coutMasters = nbMasters * prixMaster;
      var coutPassages = nbPassages * prixPassage;
      var coutPapier = nb_f * prixPapier;

      price = coutMasters + coutPassages + coutPapier;

      if (prixMaster === 0 && prixPassage === 0 && prixPapier === 0) {
        detailCalcul = `
                <div class="price-detail" style="font-size: 0.9em; color: red; margin-top: 5px;">
                    <strong>⚠️ Erreur :</strong> Les prix ne sont pas disponibles dans la base de données.<br>
                    Veuillez vérifier la configuration des prix.
                </div>
            `;
        price = 0;
      } else {
        detailCalcul = `
                <div class="price-detail" style="font-size: 0.9em; color: #666; margin-top: 5px;">
                    <strong>${S.calculation_detail} :</strong><br>
                    • ${nbMasters} masters × ${prixMaster.toFixed(2)}€ = ${coutMasters.toFixed(2)}€<br>
                    • ${nbPassages} passages × ${prixPassage.toFixed(2)}€ = ${coutPassages.toFixed(2)}€<br>
                    • ${nb_f.toFixed(0)} feuilles papier × ${prixPapier.toFixed(2)}€ = ${coutPapier.toFixed(2)}€<br>
                    <strong>Total : ${price.toFixed(2)}€</strong>
                </div>
            `;
      }

    } else if (typeRadio.value === 'photocopieur') {
      var brochures = machineElement.querySelectorAll('.brochure-item');
      var totalExemplaires = 0;

      brochures.forEach(function (brochure) {
        var nbExemplaires = parseFloat(brochure.querySelector('input[name*="[nb_exemplaires]"]').value) || 0;
        var nbFeuilles = parseFloat(brochure.querySelector('input[name*="[nb_feuilles]"]').value) || 0;
        var taille = brochure.querySelector('input[name*="[taille]"]:checked').value;
        var rv = brochure.querySelector('input[name*="[rv]"]').checked;
        var couleur = brochure.querySelector('input[name*="[couleur]"]').checked;
        var feuilles_payees = brochure.querySelector('input[name*="[feuilles_payees]"]') ? brochure.querySelector('input[name*="[feuilles_payees]"]').checked : false;

        var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;

        var photocopName = machineElement.querySelector('select[name*="[machine]"]').value;
        var prixEncre = 0;

        var fillRateElement = machineElement.querySelector('#fill_rate_photocop_' + machineIndex);
        var fillRate = fillRateElement ? parseFloat(fillRateElement.value) : 0.5;

        if (fillRate > 1.0) {
          fillRate = fillRate / 100.0;
        }

        var fillRateMultiplier = couleur ? (fillRate / 0.5) : 1.0;

        var machineKey = findMachinePriceKey(photocopName);
        console.log('🔑 Clé trouvée pour', photocopName, ':', machineKey);

        if (machineKey && prixData[machineKey]) {
          var machinePrices = prixData[machineKey];

           if (photocopName.toLowerCase() === 'comcolor') {
               if (couleur) {
                   prixEncre += (machinePrices['bleue']?.unite || 0) * fillRateMultiplier;
                   prixEncre += (machinePrices['couleur']?.unite || 0) * fillRateMultiplier;
                   prixEncre += (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                   prixEncre += (machinePrices['rouge']?.unite || 0) * fillRateMultiplier;
                   
                   prixEncre += (machinePrices['noire']?.unite || 0);
               } else {
                   prixEncre += (machinePrices['noire']?.unite || 0);
               }
           } else if (photocopName.toLowerCase() === 'konika') {
               if (couleur) {
                   prixEncre += (machinePrices['cyan']?.unite || 0) * fillRateMultiplier;
                   prixEncre += (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                   prixEncre += (machinePrices['magenta']?.unite || 0) * fillRateMultiplier;
                   
                   prixEncre += (machinePrices['noir']?.unite || 0);
                   prixEncre += (machinePrices['tambour']?.unite || 0);
                   prixEncre += (machinePrices['dev']?.unite || 0);
               } else {
                   prixEncre += (machinePrices['noir']?.unite || 0);
                   prixEncre += (machinePrices['tambour']?.unite || 0);
                   prixEncre += (machinePrices['dev']?.unite || 0);
               }
           }
        }

        if (taille === 'A4') prixEncre = prixEncre / 2;

        var nbPages = nbExemplaires * nbFeuilles;
        var coutPapier = feuilles_payees ? 0 : (nbPages * prixPapier);

        var nbPagesExactInput = brochure.querySelector('input[name*="[nb_pages]"]');
        var nbFacesTotalEncre = nbPagesExactInput ? (nbExemplaires * parseFloat(nbPagesExactInput.value)) : (rv ? nbPages * 2 : nbPages);

        var coutEncre = nbFacesTotalEncre * prixEncre;
        var coutBrochure = coutPapier + coutEncre;

        console.log(`Brochure ${taille}: exemplaires=${nbExemplaires}, feuilles=${nbFeuilles}, rv=${rv}, nbPages=${nbPages}, prixPapier=${prixPapier}, prixEncre=${prixEncre}, coutBrochure=${coutBrochure}`);

        price += coutBrochure;

        totalExemplaires += nbExemplaires;
      });

      var prixPapierMoyen = 0;
      var prixEncreMoyen = 0;
      var totalPages = 0;
      var totalPagesEncre = 0;
      var coutEncreTotal = 0;
      var coutPapierTotal = 0;
      var detailEncre = '';

      brochures.forEach(function (brochure) {
        var nbExemplaires = parseFloat(brochure.querySelector('input[name*="[nb_exemplaires]"]').value) || 0;
        var nbFeuilles = parseFloat(brochure.querySelector('input[name*="[nb_feuilles]"]').value) || 0;
        var taille = brochure.querySelector('input[name*="[taille]"]:checked').value;
        var couleur = brochure.querySelector('input[name*="[couleur]"]').checked;
        var rv = brochure.querySelector('input[name*="[rv]"]').checked;

        var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;
        var prixEncre = 0;
        var detailEncreBrochure = '';

        var photocopName = machineElement.querySelector('select[name*="[machine]"]').value;

        var fillRateElement = machineElement.querySelector('#fill_rate_photocop_' + machineIndex);
        var fillRate = fillRateElement ? parseFloat(fillRateElement.value) : 0.5;

        if (fillRate > 1.0) {
          fillRate = fillRate / 100.0;
        }

        var fillRateMultiplier = couleur ? (fillRate / 0.5) : 1.0;

        var machineKey = findMachinePriceKey(photocopName);
        console.log('🔑 Clé trouvée pour le détail', photocopName, ':', machineKey);

        if (machineKey && prixData[machineKey]) {
          var machinePrices = prixData[machineKey];

           if (photocopName.toLowerCase() === 'comcolor') {
               if (couleur) {
                   var bleue = (machinePrices['bleue']?.unite || 0) * fillRateMultiplier;
                   var couleurPrice = (machinePrices['couleur']?.unite || 0) * fillRateMultiplier;
                   var jaune = (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                   var noire = machinePrices['noire']?.unite || 0;
                   var rouge = (machinePrices['rouge']?.unite || 0) * fillRateMultiplier;
 
                   prixEncre = bleue + couleurPrice + jaune + noire + rouge;
 
                   var prixEncrePourDetail = prixEncre;
                   if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
 
                   var bleueDetail = taille === 'A4' ? bleue / 2 : bleue;
                   var couleurPriceDetail = taille === 'A4' ? couleurPrice / 2 : couleurPrice;
                   var jauneDetail = taille === 'A4' ? jaune / 2 : jaune;
                   var noireDetail = taille === 'A4' ? noire / 2 : noire;
                   var rougeDetail = taille === 'A4' ? rouge / 2 : rouge;
 
                   detailEncreBrochure = `Bleue: ${bleueDetail.toFixed(4)}€ + Couleur: ${couleurPriceDetail.toFixed(4)}€ + Jaune: ${jauneDetail.toFixed(4)}€ + Noire: ${noireDetail.toFixed(4)}€ (fixe) + Rouge: ${rougeDetail.toFixed(4)}€ = ${prixEncrePourDetail.toFixed(4)}€`;
               } else {
                   prixEncre = machinePrices['noire']?.unite || 0;
 
                   var prixEncrePourDetail = prixEncre;
                   if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
 
                   detailEncreBrochure = `Noire: ${prixEncrePourDetail.toFixed(4)}€`;
               }
           } else if (photocopName.toLowerCase() === 'konika') {
               if (couleur) {
                   var cyan = (machinePrices['cyan']?.unite || 0) * fillRateMultiplier;
                   var jaune = (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                   var magenta = (machinePrices['magenta']?.unite || 0) * fillRateMultiplier;
                   var noir = machinePrices['noir']?.unite || 0;
                   var tambour = machinePrices['tambour']?.unite || 0;
                   var dev = machinePrices['dev']?.unite || 0;
 
                   prixEncre = cyan + jaune + magenta + noir + tambour + dev;
 
                   var prixEncrePourDetail = prixEncre;
                   if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
 
                   var cyanDetail = taille === 'A4' ? cyan / 2 : cyan;
                   var jauneDetail = taille === 'A4' ? jaune / 2 : jaune;
                   var magentaDetail = taille === 'A4' ? magenta / 2 : magenta;
                   var noirDetail = taille === 'A4' ? noir / 2 : noir;
                   var tambourDetail = taille === 'A4' ? tambour / 2 : tambour;
                   var devDetail = taille === 'A4' ? dev / 2 : dev;
 
                   detailEncreBrochure = `Cyan: ${cyanDetail.toFixed(4)}€ + Jaune: ${jauneDetail.toFixed(4)}€ + Magenta: ${magentaDetail.toFixed(4)}€ + Noir: ${noirDetail.toFixed(4)}€ (fixe) + Tambour: ${tambourDetail.toFixed(4)}€ (fixe) + Dev: ${devDetail.toFixed(4)}€ (fixe) = ${prixEncrePourDetail.toFixed(4)}€`;
               } else {
                   var noir = machinePrices['noir']?.unite || 0;
                   var tambour = machinePrices['tambour']?.unite || 0;
                   var dev = machinePrices['dev']?.unite || 0;
 
                   prixEncre = noir + tambour + dev;
 
                   var prixEncrePourDetail = prixEncre;
                   if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
 
                   var noirDetail = taille === 'A4' ? noir / 2 : noir;
                   var tambourDetail = taille === 'A4' ? tambour / 2 : tambour;
                   var devDetail = taille === 'A4' ? dev / 2 : dev;
 
                   detailEncreBrochure = `Noir: ${noirDetail.toFixed(4)}€ + Tambour: ${tambourDetail.toFixed(4)}€ + Dev: ${devDetail.toFixed(4)}€ = ${prixEncrePourDetail.toFixed(4)}€`;
               }
           }
        }

        if (taille === 'A4') prixEncre = prixEncre / 2;

        var nbPages = nbExemplaires * nbFeuilles;

        var nbPagesExactInput = brochure.querySelector('input[name*="[nb_pages]"]');
        var nbPagesEncre = nbPagesExactInput ? (nbExemplaires * parseFloat(nbPagesExactInput.value)) : (rv ? nbPages * 2 : nbPages);

        var coutEncreBrochure = nbPagesEncre * prixEncre;

        prixPapierMoyen += prixPapier;
        prixEncreMoyen += prixEncre;
        totalPages += nbPages;
        totalPagesEncre += nbPagesEncre;
        coutEncreTotal += coutEncreBrochure;

        var coutPapierBrochure = feuilles_payees ? 0 : (nbPages * prixPapier);
        coutPapierTotal += coutPapierBrochure;

        if (detailEncreBrochure) {
            detailEncre += `<br>&nbsp;&nbsp;&nbsp;&nbsp;${detailEncreBrochure}`;
        }
      });

      if (brochures.length > 0) {
        prixPapierMoyen = prixPapierMoyen / brochures.length;
        prixEncreMoyen = prixEncreMoyen / brochures.length;
      }

      const feuillesParExemplaire = totalExemplaires > 0 ? totalPages / totalExemplaires : 0;
      const feuillesParExemplaireText = Number.isInteger(feuillesParExemplaire) ? feuillesParExemplaire : feuillesParExemplaire.toFixed(2);
      const totalPagesText = Number.isInteger(totalPages) ? totalPages : totalPages.toFixed(2);
      detailCalcul = `
            <div class="price-detail" style="font-size: 0.9em; color: #666; margin-top: 5px;">
                <strong>Détail du calcul :</strong><br>
                • ${totalExemplaires} exemplaires × ${feuillesParExemplaireText} feuilles = ${totalPagesText} pages<br>
                • Papier : ${totalPages} feuilles × ${prixPapierMoyen.toFixed(3)}€ = ${coutPapierTotal.toFixed(2)}€<br>
                • Encre : ${totalPagesEncre} pages × ${prixEncreMoyen.toFixed(4)}€ = ${coutEncreTotal.toFixed(2)}€${detailEncre}<br>
                <strong>Total : ${price.toFixed(2)}€</strong>
        </div>
    `;
    }

    var priceElement = machineElement.querySelector('.machine-price');
    console.log("🔍 Élément .machine-price trouvé:", priceElement ? "oui" : "non");
    if (priceElement) {
      priceElement.innerHTML = price.toFixed(2) + '€' + detailCalcul;
      console.log("✅ Prix mis à jour dans l'élément:", price.toFixed(2) + '€');
    } else {
      console.log("❌ ERREUR: Élément .machine-price non trouvé pour machine", machineIndex);
      var priceElementById = document.getElementById('machine-price-' + machineIndex);
      console.log("🔍 Élément #machine-price-" + machineIndex + " trouvé:", priceElementById ? "oui" : "non");
      if (priceElementById) {
        priceElementById.innerHTML = price.toFixed(2) + '€' + detailCalcul;
        console.log("✅ Prix mis à jour par ID:", price.toFixed(2) + '€');
      }
    }

    console.log(`🔍 Prix final retourné pour machine ${machineIndex}: ${price.toFixed(2)}€`);
    return price;
  }

  function calculateTotalPrice() {
    console.log("🔍 calculateTotalPrice appelé");
    var total = 0;
    var machineElements = document.querySelectorAll('.machine-item');
    console.log("🔍 machineElements trouvés:", machineElements.length);

    if (machineElements.length === 0) {
      console.log("❌ ERREUR: Aucune machine trouvée avec la classe .machine-item");
      return;
    }

    machineElements.forEach(function (machineElement) {
      var machineIndex = machineElement.getAttribute('data-index');
      console.log("🔍 machineIndex:", machineIndex);
      var price = calculateMachinePrice(machineIndex);
      console.log("🔍 prix calculé pour index", machineIndex, ":", price);
      total += price;

      updatePanelPreview(machineIndex);
    });

    console.log("Total final:", total);

    var prixTotalElement = document.getElementById('prix-total');
    if (prixTotalElement) {
      prixTotalElement.textContent = total.toFixed(2) + '€';
    } else {
      console.log("Élément #prix-total non trouvé");
    }

    var payeOui = document.getElementById('payeoui');
    if (payeOui && payeOui.checked) {
      var cbField = document.getElementById('cb1');
      if (cbField) {
        cbField.value = total.toFixed(2);
      }
    }

    return total;
  }

  function initializeMachinePriceCache() {
    console.log('🔄 Initialisation du cache des mappings machine...');
    window.machinePriceCache = CONFIG.machine_price_mappings || {};
    console.log('✅ Cache des mappings initialisé côté serveur:', window.machinePriceCache);
  }

  // =========================================================================
  // MONTANT DE PAIEMENT & UTILITAIRES
  // =========================================================================

  function updatePaymentAmount() {
    console.log("updatePaymentAmount appelé");
    var payeOui = document.getElementById('payeoui');
    var cbField = document.getElementById('cb1');

    if (!payeOui || !cbField) {
      console.log("Éléments payeOui ou cbField non trouvés");
      return;
    }

    if (payeOui.checked) {
      var prixTotalElement = document.getElementById('prix-total');
      if (prixTotalElement) {
        var totalText = prixTotalElement.textContent;
        var cleanedTotal = cleanNumberString(totalText);
        if (!isNaN(cleanedTotal)) {
          cbField.value = cleanedTotal.toFixed(2);
          console.log("Prix total trouvé dans #prix-total:", cleanedTotal);
          return;
        }
      }

      var totalPriceElement = document.querySelector('h2.text-primary strong');
      if (totalPriceElement) {
        var totalText = totalPriceElement.textContent;
        console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.prix_trouv__dans_h2_text_prima'] || "Prix trouvé dans h2.text-primary strong:"), totalText);
        var cleanedTotal = cleanNumberString(totalText);
        if (!isNaN(cleanedTotal)) {
          console.log("Prix total extrait:", cleanedTotal);
          cbField.value = cleanedTotal.toFixed(2);
          return;
        }
      }

      console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.aucun_prix_total_trouv'] || "Aucun prix total trouvé"));
    } else {
      cbField.value = '';
      console.log("cbField.value vidé");
    }
  }

  function cleanNumberString(value) {
    if (!value) return NaN;
    var normalized = value.replace(/\s+/g, '').replace(',', '.');
    var match = normalized.match(/-?\d+(\.\d+)?/);
    return match ? parseFloat(match[0]) : NaN;
  }

  // =========================================================================
  // COMPTEURS DUPLICOPIEUR & TAMBOURS
  // =========================================================================

  function updateDuplicopieurCounters(duplicopieurId, machineIndex) {
    console.log('🔧 updateDuplicopieurCounters appelée avec ID:', duplicopieurId, 'Index:', machineIndex);
    console.log('🔍 jQuery disponible:', typeof $ !== 'undefined');

    if (!duplicopieurId) {
      console.log('❌ Pas d\'ID duplicopieur fourni');
      return;
    }

    var selectElement = document.querySelector('select[name="machines[' + machineIndex + '][duplicopieur_id]"]');
    var selectedOption = selectElement.options[selectElement.selectedIndex];
    var machineName = selectedOption.getAttribute('data-name');

    console.log('🔍 Nom de la machine récupéré:', machineName);

    if (!machineName) {
      console.log('❌ Pas de nom de machine trouvé');
      return;
    }

    console.log('🌐 Appel AJAX vers: ?tirage_multimachines&ajax=get_last_counters&machine=' + encodeURIComponent(machineName));

    loadTamboursForDuplicopieur(duplicopieurId, machineIndex);

    $.get('?tirage_multimachines&ajax=get_last_counters&machine=' + encodeURIComponent(machineName))
      .done(function (response) {
        console.log('✅ Réponse AJAX reçue:', response);
        if (response.success) {
          console.log('📊 Compteurs reçus:', response.counters);
          $('#master_av_' + machineIndex).val(response.counters.master_av || 0);
          $('#passage_av_' + machineIndex).val(response.counters.passage_av || 0);

          console.log('🔄 Compteurs mis à jour - Master:', response.counters.master_av, 'Passage:', response.counters.passage_av);

          if (typeof calculateTotalPrice === 'function') {
            calculateTotalPrice();
          }
        } else {
          console.log('❌ Réponse AJAX indique un échec:', response);
        }
      })
      .fail(function (xhr, status, error) {
        console.log('❌ Erreur AJAX:', xhr.responseText);
        console.log('❌ Status:', status);
        console.log('❌ Error:', error);
      });
  }

  function translateTambour(tambour) {
    const translations = {
      'tambour_noir': 'Tambour Noir',
      'tambour_rouge': 'Tambour Rouge',
      'tambour_bleu': 'Tambour Bleu',
      'tambour_vert': 'Tambour Vert',
      'tambour_jaune': 'Tambour Jaune'
    };
    return translations[tambour] || tambour.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  function loadTamboursForDuplicopieur(duplicopieurId, machineIndex) {
    console.log('🥁 Chargement des tambours pour duplicopieur ID:', duplicopieurId);

    $.get('?tirage_multimachines&ajax=get_tambours&duplicopieur_id=' + duplicopieurId)
      .done(function (response) {
        console.log('✅ Tambours reçus:', response);
        if (response.success && response.tambours) {
          var tambourSelect = $('#tambour-select-' + machineIndex);
          var tambourGroup = $('#tambour-group-' + machineIndex);

          tambourSelect.empty();

          response.tambours.forEach(function (tambour, index) {
            var tambourLabel = translateTambour(tambour);
            var option = $('<option></option>')
              .attr('value', tambour)
              .text(tambourLabel);

            if (index === 0) {
              option.attr('selected', 'selected');
            }

            tambourSelect.append(option);
          });

          if (response.tambours.length > 1) {
            tambourGroup.show();
            tambourSelect.prop('required', true);
          } else {
            tambourGroup.hide();
            tambourSelect.prop('required', false);
            tambourSelect.val(response.tambours[0]);
          }

          console.log('🎯 Tambours chargés:', response.tambours.length, 'tambour(s)');

          tambourSelect.off('change.tambour').on('change.tambour', function () {
            console.log('🥁 Tambour changé, recalcul du prix pour index:', machineIndex);
            if (typeof calculateTotalPrice === 'function') {
              calculateTotalPrice();
            }
            updatePanelPreview(machineIndex);
          });

          if (typeof calculateTotalPrice === 'function') {
            calculateTotalPrice();
          }
          updatePanelPreview(machineIndex);
        } else {
          console.log('❌ Erreur lors du chargement des tambours:', response.error);
        }
      })
      .fail(function (xhr, status, error) {
        console.log('❌ Erreur AJAX pour les tambours:', status, error);
      });
  }

  // =========================================================================
  // UI – ONGLETS, PANNEAUX, PREVIEW
  // =========================================================================

  function selectMachineTypeTab(machineIndex, type) {
    console.log('Sélection onglet:', type, (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.pour_machine'] || 'pour machine:'), machineIndex);

    const tabDupli = document.getElementById('tab-duplicopieur-' + machineIndex);
    const tabPhoto = document.getElementById('tab-photocopieur-' + machineIndex);

    if (tabDupli && tabPhoto) {
      if (type === 'duplicopieur') {
        tabDupli.classList.add('active');
        tabPhoto.classList.remove('active');
      } else {
        tabPhoto.classList.add('active');
        tabDupli.classList.remove('active');
      }
    }

    const radioDupli = document.getElementById('radio-duplicopieur-' + machineIndex);
    const radioPhoto = document.getElementById('radio-photocopieur-' + machineIndex);

    if (radioDupli && radioPhoto) {
      if (type === 'duplicopieur') {
        radioDupli.checked = true;
      } else {
        radioPhoto.checked = true;
      }
    }

    toggleMachineType(machineIndex);
  }

  function toggleMachinePanel(machineIndex) {
    const content = document.getElementById('machine-content-' + machineIndex);
    const icon = document.getElementById('toggle-icon-' + machineIndex);
    const panel = document.querySelector('.machine-item[data-index="' + machineIndex + '"]');

    if (content && icon) {
      if (content.style.display === 'none') {
        $(content).slideDown(300);
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
        panel.classList.add('panel-expanded');
      } else {
        $(content).slideUp(300);
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
        panel.classList.remove('panel-expanded');
      }
    }
  }

  function updatePanelPreview(machineIndex) {
    console.log("🔍 updatePanelPreview appelé pour machine", machineIndex);
    const pricePreview = document.getElementById('price-preview-' + machineIndex);
    const typeBadge = document.getElementById('type-badge-' + machineIndex);

    console.log("🔍 Éléments trouvés:", {
      pricePreview: pricePreview ? "oui" : "non",
      typeBadge: typeBadge ? "oui" : "non"
    });

    const typeRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"]:checked`);
    if (typeBadge && typeRadio) {
      typeBadge.textContent = typeRadio.value === 'duplicopieur' ? 'Duplicopieur' : 'Photocopieur';
      console.log("✅ Type mis à jour:", typeRadio.value);
    }

    if (pricePreview) {
      const price = calculateMachinePrice(machineIndex);
      pricePreview.textContent = price.toFixed(2) + '€';
      console.log("✅ Prix preview mis à jour:", price.toFixed(2) + '€');
    } else {
      console.log("❌ ERREUR: price-preview-" + machineIndex + " non trouvé");
    }
  }

  function updateFillRateDisplay(prefix, machineIndex) {
    var slider = document.getElementById('fill_rate_' + prefix + '_slider_' + machineIndex);
    var display = document.getElementById('fill_rate_' + prefix + '_display_' + machineIndex);
    var hidden = document.getElementById('fill_rate_' + prefix + '_' + machineIndex);

    if (slider && display && hidden) {
      var value = parseInt(slider.value);
      var percentage = value + '%';
      var fillRate = (value / 100).toFixed(2);

      display.textContent = percentage;
      hidden.value = fillRate;

      calculateTotalPrice();
    }
  }

  function toggleFillRateDisplay(machineIndex) {
    var fillRateGroup = document.getElementById('fill-rate-group-' + machineIndex);
    var couleurCheckbox = document.getElementById('couleur_' + machineIndex + '_0');

    if (fillRateGroup && couleurCheckbox) {
      if (couleurCheckbox.checked) {
        fillRateGroup.style.display = 'block';
      } else {
        fillRateGroup.style.display = 'none';
      }
    }
  }

  // =========================================================================
  // PROTECTION CONTRE LA SORTIE DU FORMULAIRE DE PAIEMENT
  // =========================================================================

  let isOnPaymentForm = false;

  function showLeaveConfirmModal(targetUrl) {
    const modal = document.getElementById('confirmLeaveModal');
    if (!modal) {
      console.error('❌ Modale confirmLeaveModal introuvable');
      return;
    }

    modal.style.display = 'block';

    const btnStay = document.getElementById('btnStay');
    if (btnStay) {
      btnStay.onclick = function () {
        modal.style.display = 'none';
        console.log('✅ Utilisateur a choisi de rester sur la page');
      };
    }

    const btnLeave = document.getElementById('btnLeave');
    if (btnLeave) {
      btnLeave.onclick = function () {
        console.log('⚠️ Utilisateur a choisi de quitter - désactivation de la protection');
        isOnPaymentForm = false;
        modal.style.display = 'none';
        window.location.href = targetUrl;
      };
    }
  }

  // Expose for onclick in HTML
  window.returnToForm = returnToForm;
  window.selectMachineTypeTab = selectMachineTypeTab;
  window.toggleMachinePanel = toggleMachinePanel;
  window.toggleSaisieMode = toggleSaisieMode;
  window.toggleMachineType = toggleMachineType;
  window.updateTotalFeuilles = updateTotalFeuilles;
  window.updatePaymentAmount = updatePaymentAmount;
  window.updateDuplicopieurCounters = updateDuplicopieurCounters;
  window.loadTamboursForDuplicopieur = loadTamboursForDuplicopieur;
  window.updateFillRateDisplay = updateFillRateDisplay;
  window.toggleFillRateDisplay = toggleFillRateDisplay;
  window.calculateTotalPrice = calculateTotalPrice;

  // =========================================================================
  // INITIALISATION AU CHARGEMENT
  // =========================================================================

  document.addEventListener('DOMContentLoaded', function () {
    console.log('🔍 DOM chargé, initialisation des prix...');

    initializeMachinePriceCache();
    initAutoSave();

    const urlParams = new URLSearchParams(window.location.search);
    const shouldRestore = urlParams.get('retour') === '1' && sessionStorage.getItem('tirage_multimachines_form_data');

    if (shouldRestore) {
      console.log('🔄 Restauration des données du formulaire depuis la page de confirmation...');
      setTimeout(() => {
        const restored = restoreFormData();
        if (restored) {
          console.log('✅ Données restaurées, recalcul du prix...');
          setTimeout(() => {
            calculateTotalPrice();
            if (window.history && window.history.replaceState) {
              window.history.replaceState({}, '', '?tirage_multimachines');
            }
          }, 300);
        } else {
          calculateTotalPrice();
        }
      }, 200);
    } else {
      calculateTotalPrice();
    }

    const addMachineBtn = document.getElementById('add-machine');
    if (!addMachineBtn) {
      console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.bouton_add_machine_non_trouv'] || 'Bouton add-machine non trouvé - probablement sur la page de confirmation'));
      return;
    }

    addMachineBtn.addEventListener('click', function () {
      const container = document.getElementById('machines-container');
      const newIndex = machineCount;

      fetch(`?get-machine-template&index=${newIndex}`)
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            console.error('Erreur:', data.error);
            showAppModal({ message: 'Erreur lors de l\(window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.ajout_de_la_machine'] || 'ajout de la machine: ') + data.error, type: 'danger' });
            return;
          }

          console.log('HTML reçu de l\'endpoint:', data.html.substring(0, 200) + '...');

          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = data.html;

          console.log('tempDiv.children.length:', tempDiv.children.length);
          console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.tous_les_enfants'] || 'Tous les enfants:'), Array.from(tempDiv.children).map(el => el.tagName));

          const newMachineContainer = tempDiv.firstElementChild;

          if (!newMachineContainer) {
            console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.aucun__l_ment_trouv__dans_le_h'] || 'Aucun élément trouvé dans le HTML généré'));
            showAppModal({ message: 'Erreur lors de l\(window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.ajout_de_la_machine__html_inva'] || 'ajout de la machine: HTML invalide'), type: 'danger' });
            return;
          }

          const addButtonContainer = document.getElementById('buttons-container');

          console.log('🔍 container:', container);
          console.log('🔍 addButtonContainer:', addButtonContainer);
          console.log('🔍 container.children:', Array.from(container.children).map(el => el.className || el.tagName));

          if (addButtonContainer && container.contains(addButtonContainer)) {
            container.insertBefore(newMachineContainer, addButtonContainer);
            console.log('✅ Machine ajoutée avec succès avant le bouton!');
          } else {
            console.log('⚠️ Fallback: ajout à la fin');
            container.appendChild(newMachineContainer);
          }
          machineCount++;

          console.log('newMachineContainer HTML:', newMachineContainer.innerHTML.substring(0, 200) + '...');
          console.log('Recherche du bouton remove-machine...');

          const removeBtn = newMachineContainer.querySelector('.remove-machine');
          if (removeBtn) {
            console.log('Bouton remove-machine trouvé:', removeBtn);
            removeBtn.addEventListener('click', function () {
              newMachineContainer.remove();
              machineCount = Math.max(1, machineCount - 1);
              calculateTotalPrice();
              saveFormData();
            });
          } else {
            console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.bouton_remove_machine_non_trou'] || 'Bouton remove-machine non trouvé dans le HTML généré'));
            console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.tous_les_boutons_dans_newmachi'] || 'Tous les boutons dans newMachineContainer:'), newMachineContainer.querySelectorAll('button'));
          }

          setTimeout(() => {
            console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.appel_de_togglemachinetype_pou'] || 'Appel de toggleMachineType pour index:'), newIndex);
            console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.recherche_des__l_ments_radio'] || 'Recherche des éléments radio...'));
            const duplicopieurRadio = document.querySelector(`input[name="machines[${newIndex}][type]"][value="duplicopieur"]`);
            const photocopieurRadio = document.querySelector(`input[name="machines[${newIndex}][type]"][value="photocopieur"]`);
            console.log('duplicopieurRadio trouvé:', !!duplicopieurRadio);
            console.log('photocopieurRadio trouvé:', !!photocopieurRadio);
            toggleMachineType(newIndex);

            const duplicopieurIdField = document.querySelector(`select[name="machines[${newIndex}][duplicopieur_id]"]`) || document.querySelector(`input[name="machines[${newIndex}][duplicopieur_id]"]`);
            if (duplicopieurIdField && duplicopieurIdField.value) {
              const duplicopieurId = duplicopieurIdField.value;
              console.log('🎯 Chargement des tambours pour machine', newIndex, ', duplicopieur ID:', duplicopieurId);
              loadTamboursForDuplicopieur(duplicopieurId, newIndex);
            } else {
              console.log('⚠️ Pas de duplicopieur sélectionné pour machine', newIndex);
            }

            saveFormData();
          }, 100);

          calculateTotalPrice();
        })
        .catch(error => {
          console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.erreur_ajax'] || 'Erreur AJAX:'), error);
          console.error('Type d\'erreur:', typeof error);
          console.error('Message d\'erreur:', error.message);
          console.error('Stack trace:', error.stack);
          showAppModal({ message: 'Erreur lors de l\(window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.tirage_multimachines.ajout_de_la_machine'] || 'ajout de la machine: ') + error.message, type: 'danger' });
        });
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var payeOui = document.getElementById('payeoui');
    var payeNon = document.getElementById('payenon');

    if (payeOui) {
      payeOui.addEventListener('change', updatePaymentAmount);
    }

    if (payeNon) {
      payeNon.addEventListener('change', updatePaymentAmount);
    }

    var payeOuiInit = document.getElementById('payeoui');
    if (payeOuiInit && payeOuiInit.checked) {
      updatePaymentAmount();
    }

    toggleMachineType(0);

    var duplicopieurSelect0 = document.querySelector('select[name="machines[0][duplicopieur_id]"]');
    var duplicopieurHidden0 = document.querySelector('input[name="machines[0][duplicopieur_id]"]');
    var duplicopieurId0 = null;

    if (duplicopieurSelect0 && duplicopieurSelect0.value) {
      duplicopieurId0 = duplicopieurSelect0.value;
    } else if (duplicopieurHidden0 && duplicopieurHidden0.value) {
      duplicopieurId0 = duplicopieurHidden0.value;
    }

    if (duplicopieurId0) {
      console.log('🎯 Chargement initial des tambours pour machine 0, duplicopieur ID:', duplicopieurId0);
      loadTamboursForDuplicopieur(duplicopieurId0, 0);
    }

    calculateTotalPrice();

    const multimachinesForm = document.getElementById('multimachines-form');
    if (multimachinesForm) {
      multimachinesForm.addEventListener('submit', function () {
        var payeOui = document.getElementById('payeoui');
        var cbField = document.getElementById('cb1');
        if (payeOui && payeOui.checked && cbField) {
          var total = calculateTotalPrice();
          cbField.value = total.toFixed(2);
        }
        saveFormData();
      });
    }

    const confirmationForm = document.getElementById('form-enregistrement');
    if (confirmationForm) {
      confirmationForm.addEventListener('submit', function () {
        console.log('🧹 Validation finale : Nettoyage de la session auto_tirage et de l\'utilisateur...');
        sessionStorage.removeItem('auto_tirage_session_jobs');
        sessionStorage.removeItem('auto_tirage_session_user');
        localStorage.removeItem('auto_tirage_user');
      });
    }
  });

  $(document).ready(function () {
    var machines = document.querySelectorAll('[data-index]');
    machines.forEach(function (machine) {
      var machineIndex = machine.getAttribute('data-index');
      updateTotalFeuillesForMachine(machineIndex);
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    const paymentForm = document.getElementById('form-enregistrement');

    if (paymentForm) {
      isOnPaymentForm = true;
      console.log('📝 Formulaire de paiement détecté - Protection contre sortie activée');
    }
  });

  window.addEventListener('beforeunload', function (e) {
    if (isOnPaymentForm) {
      e.preventDefault();
      e.returnValue = '';
      return '';
    }
  });

  document.addEventListener('click', function (e) {
    const link = e.target.closest('a');

    if (link && isOnPaymentForm) {
      const btnRetour = document.getElementById('btn-retour');
      if (link === btnRetour || link.onclick || link.getAttribute('onclick')) {
        return;
      }

      e.preventDefault();
      showLeaveConfirmModal(link.href);
    }
  });

  const paymentFormElement = document.getElementById('form-enregistrement');
  if (paymentFormElement) {
    paymentFormElement.addEventListener('submit', function () {
      console.log('📤 Formulaire soumis - désactivation de la protection');
      isOnPaymentForm = false;
    });
  }

  const originalReturnToForm = window.returnToForm;
  window.returnToForm = function () {
    console.log('◀️ Retour au formulaire - désactivation de la protection');
    isOnPaymentForm = false;

    if (typeof originalReturnToForm === 'function') {
      originalReturnToForm();
    }
  };

})();
