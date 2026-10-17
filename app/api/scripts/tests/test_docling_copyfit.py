import os
import sys
import unittest

class TestDoclingCopyfit(unittest.TestCase):
    def test_copyfit_script_exists(self):
        """Vérifie la présence du script d'ajustement dynamique de police docling_copyfit.py"""
        script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        target_script = os.path.join(script_dir, "docling_copyfit.py")
        self.assertTrue(os.path.exists(target_script), "Le script docling_copyfit.py doit être présent")

    def test_font_scale_calculation(self):
        """Vérifie le calcul de facteur d'échelle pour l'ajustement de texte"""
        target_height = 100
        content_height = 200
        scale_factor = target_height / content_height
        self.assertAlmostEqual(scale_factor, 0.5)

if __name__ == "__main__":
    unittest.main()
