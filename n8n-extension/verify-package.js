#!/usr/bin/env node

/**
 * Package Verification Script for n8n Laravel Eloquent Extension
 * 
 * This script verifies that the package is properly built and configured
 * for distribution as an n8n community node.
 */

const fs = require('fs');
const path = require('path');

console.log('🔍 Verifying n8n Laravel Eloquent Extension Package...\n');

// Verification results
const results = {
  passed: 0,
  failed: 0,
  warnings: 0
};

function checkPassed(message) {
  console.log(`✅ ${message}`);
  results.passed++;
}

function checkFailed(message) {
  console.log(`❌ ${message}`);
  results.failed++;
}

function checkWarning(message) {
  console.log(`⚠️  ${message}`);
  results.warnings++;
}

// 1. Check package.json structure
console.log('📦 Checking package.json...');
try {
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  
  // Required fields
  if (packageJson.name === '@shortinc/n8n-eloquent-nodes') {
    checkPassed('Package name is correct');
  } else {
    checkFailed('Package name is incorrect');
  }
  
  if (packageJson.version) {
    checkPassed(`Version is set: ${packageJson.version}`);
  } else {
    checkFailed('Version is missing');
  }
  
  if (packageJson.main === 'dist/index.js') {
    checkPassed('Main entry point is correct');
  } else {
    checkFailed('Main entry point is incorrect');
  }
  
  if (packageJson.types === 'dist/index.d.ts') {
    checkPassed('TypeScript declarations entry point is correct');
  } else {
    checkFailed('TypeScript declarations entry point is incorrect');
  }
  
  // n8n configuration
  if (packageJson.n8n) {
    checkPassed('n8n configuration exists');
    
    if (packageJson.n8n.n8nNodesApiVersion === 1) {
      checkPassed('n8n API version is correct');
    } else {
      checkFailed('n8n API version is incorrect');
    }
    
    // Check credentials
    if (packageJson.n8n.credentials && packageJson.n8n.credentials.length > 0) {
      checkPassed(`${packageJson.n8n.credentials.length} credential(s) configured`);
    } else {
      checkFailed('No credentials configured');
    }
    
    // Check nodes
    if (packageJson.n8n.nodes && packageJson.n8n.nodes.length > 0) {
      checkPassed(`${packageJson.n8n.nodes.length} node(s) configured`);
    } else {
      checkFailed('No nodes configured');
    }
  } else {
    checkFailed('n8n configuration is missing');
  }
  
  // Keywords
  if (packageJson.keywords && packageJson.keywords.includes('n8n-community-node-package')) {
    checkPassed('Community node package keyword present');
  } else {
    checkFailed('Community node package keyword missing');
  }
  
} catch (error) {
  checkFailed(`Error reading package.json: ${error.message}`);
}

console.log();

// 2. Check dist directory structure
console.log('🏗️  Checking build output...');
const distPath = 'dist';

if (fs.existsSync(distPath)) {
  checkPassed('dist directory exists');
  
  // Check main files
  const requiredFiles = [
    'index.js',
    'index.d.ts',
    'credentials/LaravelEloquentApi.credentials.js',
    'credentials/LaravelEloquentApi.credentials.d.ts',
    'nodes/LaravelEloquentTrigger/LaravelEloquentTrigger.node.js',
    'nodes/LaravelEloquentTrigger/LaravelEloquentTrigger.node.d.ts',
    'nodes/LaravelEloquentGet/LaravelEloquentGet.node.js',
    'nodes/LaravelEloquentGet/LaravelEloquentGet.node.d.ts',
    'nodes/LaravelEloquentSet/LaravelEloquentSet.node.js',
    'nodes/LaravelEloquentSet/LaravelEloquentSet.node.d.ts'
  ];
  
  requiredFiles.forEach(file => {
    const filePath = path.join(distPath, file);
    if (fs.existsSync(filePath)) {
      checkPassed(`${file} exists`);
    } else {
      checkFailed(`${file} is missing`);
    }
  });
  
} else {
  checkFailed('dist directory does not exist - run npm run build');
}

console.log();

// 3. Check source files
console.log('📝 Checking source files...');
const sourceFiles = [
  'index.ts',
  'credentials/LaravelEloquentApi.credentials.ts',
  'nodes/LaravelEloquentTrigger/LaravelEloquentTrigger.node.ts',
  'nodes/LaravelEloquentGet/LaravelEloquentGet.node.ts',
  'nodes/LaravelEloquentSet/LaravelEloquentSet.node.ts'
];

sourceFiles.forEach(file => {
  if (fs.existsSync(file)) {
    checkPassed(`${file} exists`);
  } else {
    checkFailed(`${file} is missing`);
  }
});

console.log();

// 4. Check documentation
console.log('📚 Checking documentation...');
const docFiles = [
  'README.md',
  'SECURITY.md',
  'TESTING.md',
  'CHANGELOG.md'
];

docFiles.forEach(file => {
  if (fs.existsSync(file)) {
    const content = fs.readFileSync(file, 'utf8');
    if (content.length > 100) {
      checkPassed(`${file} exists and has content`);
    } else {
      checkWarning(`${file} exists but seems too short`);
    }
  } else {
    checkFailed(`${file} is missing`);
  }
});

console.log();

// 5. Check configuration files
console.log('⚙️  Checking configuration files...');
const configFiles = [
  'tsconfig.json',
  '.eslintrc.js'
];

configFiles.forEach(file => {
  if (fs.existsSync(file)) {
    checkPassed(`${file} exists`);
  } else {
    checkWarning(`${file} is missing`);
  }
});

console.log();

// 6. Check for common issues
console.log('🔍 Checking for common issues...');

// Check for node_modules in files array
try {
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  if (packageJson.files && packageJson.files.includes('dist')) {
    checkPassed('Only dist directory included in files array');
  } else {
    checkWarning('files array should include only dist directory');
  }
} catch (error) {
  // Already handled above
}

// Check for .gitignore
if (fs.existsSync('.gitignore')) {
  const gitignore = fs.readFileSync('.gitignore', 'utf8');
  if (gitignore.includes('node_modules')) {
    checkPassed('.gitignore includes node_modules');
  } else {
    checkWarning('.gitignore should include node_modules');
  }
  if (gitignore.includes('dist')) {
    checkWarning('.gitignore includes dist (should not for npm package)');
  }
} else {
  checkWarning('.gitignore file is missing');
}

console.log();

// Summary
console.log('📊 Verification Summary:');
console.log(`✅ Passed: ${results.passed}`);
console.log(`❌ Failed: ${results.failed}`);
console.log(`⚠️  Warnings: ${results.warnings}`);

if (results.failed === 0) {
  console.log('\n🎉 Package verification completed successfully!');
  console.log('📦 The package is ready for distribution.');
  process.exit(0);
} else {
  console.log('\n💥 Package verification failed!');
  console.log('🔧 Please fix the issues above before distributing.');
  process.exit(1);
} 