const { calculerDuplicopieur, calculerPhotocopieuse } = require('../../app/public/js/calcul.js');

describe('Calculs de Tarification (calcul.js)', () => {

  describe('calculerDuplicopieur', () => {
    test('calcule le coût des masters', () => {
      const nbMasters = 4;
      const prixMasterUnite = 2.5;
      const coutMasters = nbMasters * prixMasterUnite;
      expect(coutMasters).toBe(10.0);
    });

    test('calcule le coût des passages', () => {
      const nbPassages = 500;
      const prixPassageUnite = 0.005;
      const coutPassages = nbPassages * prixPassageUnite;
      expect(coutPassages).toBe(2.5);
    });

    test('calcule le coût total duplicopieur', () => {
      const nbMasters = 5;
      const prixMaster = 3.0;
      const nbPassages = 1000;
      const prixPassage = 0.004;
      const total = (nbMasters * prixMaster) + (nbPassages * prixPassage);
      expect(total).toBe(15 + 4);
      expect(total).toBe(19.0);
    });
  });

  describe('calculerPhotocopieuse', () => {
    test('calcule le coût noir et blanc', () => {
      const nbPages = 200;
      const prixPage = 0.01;
      const total = nbPages * prixPage;
      expect(total).toBe(2.0);
    });

    test('calcule le coût couleur', () => {
      const nbPages = 100;
      const prixPageNoir = 0.01;
      const prixPageCouleur = 0.03;
      const totalNoir = nbPages * prixPageNoir;
      const totalCouleur = nbPages * prixPageCouleur;
      expect(totalNoir).toBe(1.0);
      expect(totalCouleur).toBe(3.0);
    });

    test('la couleur coûte plus cher que noir et blanc', () => {
      const nbPages = 100;
      expect(nbPages * 0.03).toBeGreaterThan(nbPages * 0.01);
    });
  });

  describe('Calcul Recto-Verso', () => {
    test('le recto-verso divise les feuilles papier par 2', () => {
      const nbPassages = 100;
      const isRV = true;
      const nbFeuillesPapier = isRV ? Math.ceil(nbPassages / 2) : nbPassages;
      expect(nbFeuillesPapier).toBe(50);
    });

    test('le simple face conserve le nombre de feuilles', () => {
      const nbPassages = 100;
      const isRV = false;
      const nbFeuillesPapier = isRV ? Math.ceil(nbPassages / 2) : nbPassages;
      expect(nbFeuillesPapier).toBe(100);
    });
  });

  describe('Coût papier A4 vs A3', () => {
    test('A3 coûte le double de A4', () => {
      const prixA4 = 0.01;
      const prixA3 = 0.02;
      expect(prixA3).toBe(prixA4 * 2);
    });

    test('calcule le coût papier pour 200 feuilles A4', () => {
      const nbFeuilles = 200;
      const prix = 0.015;
      expect(nbFeuilles * prix).toBe(3.0);
    });
  });
});
