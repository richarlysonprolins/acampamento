exports.handler = async (event, context) => {
  // Configuração CORS para preflight
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': 'Content-Type',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
      },
      body: '',
    }
  }

  // Só processa requisições POST
  if (event.httpMethod !== 'POST') {
    return {
      statusCode: 405,
      headers: {
        'Access-Control-Allow-Origin': '*',
      },
      body: JSON.stringify({ error: 'Method Not Allowed' }),
    }
  }

  try {
    const { path } = event
    console.log('Path recebido:', path)

    // Extrai o endpoint da URL (remove /api/ se existir)
    let endpoint = path.replace('/.netlify/functions/proxy', '')
    if (endpoint.startsWith('/')) {
      endpoint = endpoint.substring(1)
    }

    const url = `https://acampamento-desbravadores.infinityfree.me/api/${endpoint}`

    console.log('Proxy: ', endpoint, '→', url)

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: event.body,
    })

    const data = await response.text()

    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Content-Type': 'application/json',
      },
      body: data,
    }
  } catch (error) {
    return {
      statusCode: 500,
      headers: {
        'Access-Control-Allow-Origin': '*',
      },
      body: JSON.stringify({ error: 'Proxy error: ' + error.message }),
    }
  }
}
