const fs = require('fs');
const path = require('path');

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

    if (!fs.existsSync(distDir)) {
      fs.mkdirSync(distDir, { recursive: true });
    }

    if (fs.existsSync(sourceFile)) {
      fs.copyFileSync(sourceFile, distFile);
      console.log(`✅ Copied ${sourceFile} to ${distFile}`);
    } else {
      console.log(`⚠️ Source file not found: ${sourceFile}`);
    }
  });
}

// Run the copy function
copySvgFiles(); 