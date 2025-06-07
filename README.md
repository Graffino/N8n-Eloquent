# shortinc/n8n-eloquent

A Laravel package for seamlessly integrating Eloquent models with n8n workflows. This package enables automatic webhook creation and management for your Eloquent models, making it easy to trigger n8n workflows on model events.

## 🤖 Development Note

This package was developed with the assistance of AI (Claude) through the Cursor IDE. While this enhances development efficiency and code quality, all implementations have been carefully reviewed and tested to ensure they meet production standards.

## Features

- 🔄 Automatic webhook registration for Eloquent models
- 📡 Real-time model event broadcasting to n8n
- 🔐 Secure webhook endpoints with HMAC verification
- ⚡ Support for model lifecycle events (create, update, delete)
- 🎯 Targeted property change tracking
- 🔍 Health monitoring and subscription management
- 🛠️ Command-line tools for setup and maintenance

## Installation

You can install the package via composer:

```bash
composer require shortinc/n8n-eloquent
```

## Basic Usage

1. Register your models in the service provider:

```php
use Shortinc\N8nEloquent\Traits\HasWebhooks;

class User extends Model
{
    use HasWebhooks;
    
    protected static $webhookEvents = [
        'created',
        'updated',
        'deleted'
    ];
}
```

2. Set up your n8n webhook URL in your `.env` file:

```env
N8N_WEBHOOK_URL=https://your-n8n-instance.com/webhook/path
N8N_WEBHOOK_SECRET=your-secret-key
```

## Documentation

For detailed documentation, please visit our [Wiki](link-to-wiki).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## Credits

- [Nick Ciolpan](https://github.com/nickciolpan)
- [n8n](https://n8n.io) - For their amazing workflow automation platform
- [Claude AI](https://anthropic.com/claude) - For development assistance through Cursor IDE
- [All Contributors](link-to-contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

<div align="center">
Built with ❤️ by Short Inc.<br>
Powered by n8n & AI
</div> 