import os
import sys
import unittest

class TestDoclingExport(unittest.TestCase):
    def test_export_script_exists(self):
        """Vérifie la présence du script d'export Docling docling_export.py"""
        script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        target_script = os.path.join(script_dir, "docling_export.py")
        self.assertTrue(os.path.exists(target_script), "Le script docling_export.py doit être présent")

if __name__ == "__main__":
    unittest.main()
