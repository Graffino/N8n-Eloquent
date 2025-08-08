const fs = require('fs');
const path = require('path');

// Function to copy SVG files from source to dist directories
function copySvgFiles() {
  const sourceDirs = [
    'nodes/LaravelEventDispatcher',
    'nodes/LaravelEventListener', 
    'nodes/LaravelEloquentCrud',
    'nodes/LaravelEloquentTrigger',
    'nodes/LaravelJobDispatcher',
    'credentials'
  ];

  sourceDirs.forEach(sourceDir => {
    const sourceFile = path.join(sourceDir, 'laravel.svg');
    const distDir = path.join('dist', sourceDir);
    const distFile = path.join(distDir, 'laravel.svg');

    // Create dist directory if it doesn't exist
    if (!fs.existsSync(distDir)) {
      fs.mkdirSync(distDir, { recursive: true });
    }

    // Copy SVG file if it exists
    if (fs.existsSync(sourceFile)) {
      fs.copyFileSync(sourceFile, distFile);
      console.log(`✅ Copied ${sourceFile} to ${distFile}`);
    } else {
      console.log(`⚠️  Source file not found: ${sourceFile}`);
    }
  });
}

// Run the copy function
copySvgFiles(); 