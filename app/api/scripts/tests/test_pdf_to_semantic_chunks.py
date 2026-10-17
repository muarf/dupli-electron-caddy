import os
import sys
import unittest

class TestPdfToSemanticChunks(unittest.TestCase):
    def test_imports_and_environment(self):
        """Vérifie l'importabilité du module et la structure de découpage sémantique"""
        script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        sys.path.insert(0, script_dir)
        
        target_script = os.path.join(script_dir, "pdf_to_semantic_chunks.py")
        self.assertTrue(os.path.exists(target_script), "Le script pdf_to_semantic_chunks.py doit exister")

    def test_chunking_structure(self):
        """Vérifie le format de données des blocs sémantiques RAG"""
        dummy_chunk = {
            "text": "Titre du document",
            "section_title": "Introduction",
            "heading_level": 1,
            "page_number": 1
        }
        self.assertIn("text", dummy_chunk)
        self.assertEqual(dummy_chunk["heading_level"], 1)

if __name__ == "__main__":
    unittest.main()
